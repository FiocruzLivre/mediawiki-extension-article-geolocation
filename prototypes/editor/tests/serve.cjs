const http = require('node:http');
const fs = require('node:fs/promises');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const types = { '.html': 'text/html', '.js': 'text/javascript', '.css': 'text/css', '.json': 'application/json' };

http.createServer(async (request, response) => {
    const pathname = decodeURIComponent(new URL(request.url, 'http://localhost').pathname);
    const filename = path.resolve(root, '.' + pathname);
    if (!filename.startsWith(root + path.sep)) {
        response.writeHead(403).end();
        return;
    }
    try {
        const data = await fs.readFile(filename);
        response.setHeader('Content-Type', types[path.extname(filename)] || 'application/octet-stream');
        response.end(data);
    } catch {
        response.writeHead(404).end();
    }
}).listen(9876, '127.0.0.1');
