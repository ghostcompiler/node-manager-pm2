const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const docsIndex = path.join(root, 'docs', 'index.html');
const readme = path.join(root, 'README.md');

function assertFile(file) {
  if (!fs.existsSync(file)) {
    throw new Error(`Missing file: ${path.relative(root, file)}`);
  }
}

function collectStaticScreenshotRefs(file) {
  const text = fs.readFileSync(file, 'utf8');
  const refs = [];

  for (const match of text.matchAll(/(?:src|href)="(screenshots\/[^"]+)"/g)) {
    refs.push(path.join(root, 'docs', match[1]));
  }

  for (const match of text.matchAll(/\]\((docs\/screenshots\/[^)]+)\)/g)) {
    refs.push(path.join(root, match[1]));
  }

  for (const match of text.matchAll(/src="(docs\/screenshots\/[^"]+)"/g)) {
    refs.push(path.join(root, match[1]));
  }

  return refs;
}

function collectDynamicScreenshotRefs() {
  const html = fs.readFileSync(docsIndex, 'utf8');
  const views = Array.from(html.matchAll(/data-view="([^"]+)"/g), match => match[1]);
  const refs = [];

  for (const view of views) {
    refs.push(path.join(root, 'docs', 'screenshots', `${view}_light.png`));
    refs.push(path.join(root, 'docs', 'screenshots', `${view}_dark.png`));
  }

  return refs;
}

assertFile(docsIndex);
assertFile(readme);

const refs = [
  ...collectStaticScreenshotRefs(docsIndex),
  ...collectStaticScreenshotRefs(readme),
  ...collectDynamicScreenshotRefs(),
];

const missing = Array.from(new Set(refs)).filter(file => !fs.existsSync(file));

if (missing.length > 0) {
  throw new Error(`Missing documentation screenshot references:\n${missing.map(file => `- ${path.relative(root, file)}`).join('\n')}`);
}

console.log(`Documentation screenshot references OK: ${new Set(refs).size}`);
