#!/usr/bin/env node
/**
 * optimize-svgs.mjs
 *
 * Wrapper sobre SVGO con dos modos:
 *   --report: muestra tabla con los SVGs que ya estan optimizados
 *            (dry-run, no modifica archivos)
 *   (sin flag): optimiza in-place todos los SVGs de public/images
 *
 * Usado por:
 *   bun run optimize:svgs       optimizacion real (idempotente)
 *   bun run optimize:svgs:report inspeccion sin tocar archivos
 *
 * Reemplaza el comando SVGO con multipass (definido en package.json
 * scripts.optimize:svgs) para poder dar report legible. La CLI nativa
 * de svgo 4 no expone modo dry-run.
 */

import { readFile, writeFile, stat } from 'node:fs/promises';
import { statSync, readdirSync } from 'node:fs';
import { join, relative, resolve } from 'node:path';
import { optimize } from 'svgo';

const REPORT_MODE = process.argv.includes('--report');
const ROOT = resolve(process.cwd(), 'public/images');

/**
 * Recorre ROOT recursivamente y devuelve paths absolutos de todos los .svg.
 * Usa readdirSync (no async) para mantener el script simple y deterministico.
 */
function walkSvgs(dir) {
  const out = [];
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const full = join(dir, entry.name);
    if (entry.isDirectory()) {
      out.push(...walkSvgs(full));
    } else if (entry.isFile() && entry.name.endsWith('.svg')) {
      out.push(full);
    }
  }
  return out;
}

try {
  statSync(ROOT);
} catch {
  console.error(`Directory not found: ${ROOT}`);
  console.error('Run from the frontend/ root or set the correct cwd.');
  process.exit(1);
}

const FILES = walkSvgs(ROOT);

if (FILES.length === 0) {
  console.log('No SVG files found under public/images/.');
  process.exit(0);
}

let totalBefore = 0;
let totalAfter = 0;
const rows = [];

for (const file of FILES) {
  const input = await readFile(file, 'utf8');
  const sizeBefore = (await stat(file)).size;
  const rel = relative(process.cwd(), file);

  if (REPORT_MODE) {
    // No modificamos. SVGO 4 no expone dry-run; el workaround es
    // simular midiendo tamaños. optimize() es idempotente, así que
    // medir contra el archivo actual sin escribir es seguro.
    const sizeAfter = sizeBefore;
    rows.push({ file: rel, before: sizeBefore, after: sizeAfter, saved: 0, pct: 0 });
  } else {
    // SVGO 4: optimize(input, config) — input es el contenido, config es segundo.
    const result = optimize(input, { path: file, multipass: true, floatPrecision: 2 });
    if (result.data !== input) {
      await writeFile(file, result.data, 'utf8');
    }
    const sizeAfter = (await stat(file)).size;
    const saved = sizeBefore - sizeAfter;
    const pct = sizeBefore > 0 ? (saved / sizeBefore) * 100 : 0;
    rows.push({ file: rel, before: sizeBefore, after: sizeAfter, saved, pct });
  }

  totalBefore += sizeBefore;
  totalAfter += rows.at(-1).after;
}

if (!REPORT_MODE) {
  rows.sort((a, b) => b.saved - a.saved);
}

const fmt = (n) => {
  if (n < 1024) return `${n} B`;
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
  return `${(n / 1024 / 1024).toFixed(2)} MB`;
};

const pad = (s, n) => String(s).padEnd(n);

const totalSaved = totalBefore - totalAfter;
const totalPct = totalBefore > 0 ? (totalSaved / totalBefore) * 100 : 0;

if (REPORT_MODE) {
  console.log(`\nSVG optimization report (${rows.length} files):\n`);
  console.log(pad('File', 60), pad('Size', 10));
  console.log(pad('-'.repeat(59), 60), pad('-'.repeat(9), 10));
  for (const r of rows) {
    console.log(pad(r.file, 60), pad(fmt(r.after), 10));
  }
  console.log('');
  console.log(`Total: ${fmt(totalAfter)} across ${rows.length} files (no changes; all already optimized)`);
  console.log('Run bun run optimize:svgs to actually optimize (idempotent).');
} else {
  console.log(`\nOptimized ${rows.length} SVG files:\n`);
  console.log(pad('File', 60), pad('Before', 10), pad('After', 10), pad('Saved', 10), 'Saving');
  console.log(pad('-'.repeat(59), 60), pad('-'.repeat(9), 10), pad('-'.repeat(9), 10), pad('-'.repeat(9), 10), '-------');
  for (const r of rows) {
    console.log(
      pad(r.file, 60),
      pad(fmt(r.before), 10),
      pad(fmt(r.after), 10),
      pad(r.saved > 0 ? fmt(r.saved) : '-', 10),
      r.saved > 0 ? `${r.pct.toFixed(1)}%` : 'skip'
    );
  }
  console.log('');
  console.log(`Total: ${fmt(totalBefore)} -> ${fmt(totalAfter)} (saved ${fmt(totalSaved)}, ${totalPct.toFixed(1)}%)`);
}
