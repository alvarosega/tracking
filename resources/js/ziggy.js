const Ziggy = {"url":"http:\/\/alvarosega.com","port":null,"defaults":{},"routes":{"sanctum.csrf-cookie":{"uri":"sanctum\/csrf-cookie","methods":["GET","HEAD"]},"login":{"uri":"login","methods":["GET","HEAD"]},"logout":{"uri":"logout","methods":["POST"]},"supervisor.index":{"uri":"supervisor","methods":["GET","HEAD"]},"supervisor.ruteo.index":{"uri":"supervisor\/ruteo","methods":["GET","HEAD"]},"supervisor.tracking.index":{"uri":"supervisor\/tracking","methods":["GET","HEAD"]},"supervisor.visitas.index":{"uri":"supervisor\/visitas","methods":["GET","HEAD"]},"storage.local":{"uri":"storage\/{path}","methods":["GET","HEAD"],"wheres":{"path":".*"},"parameters":["path"]}}};
if (typeof window !== 'undefined' && typeof window.Ziggy !== 'undefined') {
  Object.assign(Ziggy.routes, window.Ziggy.routes);
}
export { Ziggy };
