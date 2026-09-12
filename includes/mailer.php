<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/mail.php';

function residence_is_gmail(string $email): bool
{
    return (bool) preg_match('/@gmail\.com$/i', trim($email));
}

function residence_receipt_gmail(?string $formEmail = null): string
{
    $account = strtolower(trim((string) ($_SESSION['user_email'] ?? '')));
    $form = strtolower(trim((string) $formEmail));

    if (filter_var($account, FILTER_VALIDATE_EMAIL) && residence_is_gmail($account)) {
        return $account;
    }

    if (filter_var($form, FILTER_VALIDATE_EMAIL) && residence_is_gmail($form)) {
        return $form;
    }

    return $account;
}

function smtp_read_reply($fp): string
{
    $data = '';
    while (($line = fgets($fp, 515)) !== false) {
        $data .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }

    return $data;
}

function smtp_command($fp, string $command, array $okCodes): string
{
    if ($command !== '') {
        fwrite($fp, $command . "\r\n");
    }
    $reply = smtp_read_reply($fp);
    $code = (int) substr($reply, 0, 3);
    if (!in_array($code, $okCodes, true)) {
        throw new RuntimeException(trim($reply) !== '' ? trim($reply) : 'SMTP command failed.');
    }

    return $reply;
}

function smtp_connect_gmail()
{
    $timeout = 12;
    $attempts = [
        ['remote' => 'ssl://smtp.gmail.com:465', 'verify' => true],
        ['remote' => 'ssl://smtp.gmail.com:465', 'verify' => false],
        ['remote' => 'tcp://smtp.gmail.com:587', 'verify' => true, 'starttls' => true],
    ];

    $lastError = 'Could not connect to Gmail SMTP.';
    foreach ($attempts as $attempt) {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => (bool) $attempt['verify'],
                'verify_peer_name' => (bool) $attempt['verify'],
                'allow_self_signed' => false,
            ],
        ]);
        $fp = @stream_socket_client(
            $attempt['remote'],
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if (!$fp) {
            $lastError = 'Could not connect to Gmail SMTP: ' . $errstr;
            continue;
        }

        stream_set_timeout($fp, $timeout);
        try {
            smtp_command($fp, '', [220]);
            smtp_command($fp, 'EHLO addtomar.local', [250]);
            if (!empty($attempt['starttls'])) {
                smtp_command($fp, 'STARTTLS', [220]);
                $crypto = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                    $crypto |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                }
                if (!stream_socket_enable_crypto($fp, true, $crypto)) {
                    throw new RuntimeException('Could not start TLS with Gmail SMTP.');
                }
                smtp_command($fp, 'EHLO addtomar.local', [250]);
            }

            return $fp;
        } catch (Throwable $e) {
            fclose($fp);
            $lastError = $e->getMessage();
        }
    }

    throw new RuntimeException($lastError);
}

function smtp_auth_gmail($fp, string $user, string $pass): void
{
    try {
        smtp_command($fp, 'AUTH LOGIN', [334]);
        smtp_command($fp, base64_encode($user), [334]);
        smtp_command($fp, base64_encode($pass), [235]);
        return;
    } catch (Throwable $loginError) {
        $plain = base64_encode("\0" . $user . "\0" . $pass);
        try {
            smtp_command($fp, 'AUTH PLAIN ' . $plain, [235]);
            return;
        } catch (Throwable) {
            throw $loginError;
        }
    }
}

function smtp_friendly_error(string $message): string
{
    if (str_contains($message, '5.7.8') || stripos($message, 'Username and Password not accepted') !== false) {
        $mailbox = trim((string) MAIL_SMTP_USER);
        return 'Gmail rejected the mailbox login for ' . $mailbox . '. Sign in to that Gmail, open Google Account → Security → 2-Step Verification → App passwords, create a new App Password, and paste it into config/mail.local.php as MAIL_SMTP_PASS.';
    }

    return $message;
}

function smtp_send_html(string $to, string $subject, string $html, string $text = ''): array
{
    $to = strtolower(trim($to));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Enter a valid email address.'];
    }

    $user = trim((string) MAIL_SMTP_USER);
    $pass = preg_replace('/\s+/', '', trim((string) MAIL_SMTP_PASS)) ?? '';
    if ($user === '' || $pass === '') {
        return ['ok' => false, 'error' => 'Add your Gmail App Password in config/mail.local.php so emails can be sent.'];
    }

    $from = $user;
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $text = trim($text);
    if ($text === '') {
        $text = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    $boundary = 'addtomar-' . bin2hex(random_bytes(8));
    $headers = [
        'Date: ' . date('r'),
        'From: AddToMar <' . $from . '>',
        'To: ' . $to,
        'Reply-To: ' . $from,
        'Subject: ' . $encodedSubject,
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];
    $payload = implode("\r\n", $headers) . "\r\n\r\n"
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $text . "\r\n\r\n"
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $html . "\r\n\r\n"
        . '--' . $boundary . '--';

    $fp = null;
    try {
        $fp = smtp_connect_gmail();
        smtp_auth_gmail($fp, $user, $pass);
        smtp_command($fp, 'MAIL FROM:<' . $from . '>', [250]);
        smtp_command($fp, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtp_command($fp, 'DATA', [354]);
        fwrite($fp, $payload . "\r\n.\r\n");
        $reply = smtp_read_reply($fp);
        $code = (int) substr($reply, 0, 3);
        if ($code !== 250) {
            throw new RuntimeException(trim($reply) !== '' ? trim($reply) : 'Gmail rejected the message.');
        }
        smtp_command($fp, 'QUIT', [221, 250]);
        fclose($fp);

        return ['ok' => true, 'error' => ''];
    } catch (Throwable $e) {
        if ($fp) {
            fclose($fp);
        }

        return ['ok' => false, 'error' => smtp_friendly_error($e->getMessage())];
    }
}

function smtp_send_gmail(string $to, string $subject, string $html): array
{
    $to = strtolower(trim($to));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !residence_is_gmail($to)) {
        return ['ok' => false, 'error' => 'Receipts can only be sent to a Gmail address.'];
    }

    return smtp_send_html($to, $subject, $html);
}
