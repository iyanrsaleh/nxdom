import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const searchPath = path.join(root, 'controllers/Frontend/Data/Search.php');
const fragPath = path.join(root, 'scripts/doc-search-items.fragment.php');

let search = fs.readFileSync(searchPath, 'utf8');
let frag = fs.readFileSync(fragPath, 'utf8').replace(/^\/\/ Generated.*\r?\n/, '');

const markerStart = '$items = [';
const markerEnd = '\n        ];';
const start = search.indexOf(markerStart);
if (start < 0) throw new Error('start marker not found');
const contentStart = start + markerStart.length;
const end = search.indexOf(markerEnd, contentStart);
if (end < 0) throw new Error('end marker not found');
search = search.slice(0, contentStart) + '\n' + frag.trimEnd() + search.slice(end);

fs.writeFileSync(searchPath, search, 'utf8');
console.log('Patched', searchPath);
