const RENDER_ORIGIN = 'https://addtomar.onrender.com';

export const config = {
  matcher: ['/:path*'],
};

export default async function middleware(request) {
  const incoming = new URL(request.url);
  let path = incoming.pathname;
  if (path === '/login' || path === '/login/') {
    path = '/login.php';
  }

  const headers = new Headers(request.headers);
  headers.set('X-Forwarded-Host', incoming.host);
  headers.set('X-Forwarded-Proto', 'https');
  headers.delete('accept-encoding');

  const init = {
    method: request.method,
    headers,
    redirect: 'manual',
  };

  if (request.method !== 'GET' && request.method !== 'HEAD') {
    init.body = request.body;
    init.duplex = 'half';
  }

  const upstream = await fetch(RENDER_ORIGIN + path + incoming.search, init);
  const outHeaders = new Headers(upstream.headers);
  const location = outHeaders.get('location');
  if (location) {
    outHeaders.set(
      'location',
      location.replaceAll(RENDER_ORIGIN, incoming.origin).replaceAll('http://addtomar.onrender.com', incoming.origin)
    );
  }

  return new Response(upstream.body, {
    status: upstream.status,
    statusText: upstream.statusText,
    headers: outHeaders,
  });
}
