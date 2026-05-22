import fs from "node:fs";
import path from "node:path";

const projectRoot = process.cwd();
const allowedSpecifier = "NexaJS";
const extensions = new Set([".js", ".jsx", ".ts", ".tsx", ".mjs", ".cjs"]);
const ignoreDirs = new Set([
  "node_modules",
  ".git",
  "android",
  "ios",
  "dist",
  "build",
  ".expo",
  ".next",
]);

const importRegex =
  /(?:import\s+[^'"]*?\s+from\s*|import\s*\()\s*["']([^"']+)["']/g;

function shouldCheckSpecifier(specifier) {
  const normalized = specifier.toLowerCase();
  return normalized === "nexajs";
}

function walk(dirPath, files = []) {
  const entries = fs.readdirSync(dirPath, { withFileTypes: true });
  for (const entry of entries) {
    const fullPath = path.join(dirPath, entry.name);
    if (entry.isDirectory()) {
      if (!ignoreDirs.has(entry.name)) {
        walk(fullPath, files);
      }
      continue;
    }
    if (extensions.has(path.extname(entry.name))) {
      files.push(fullPath);
    }
  }
  return files;
}

function validateFile(filePath) {
  const content = fs.readFileSync(filePath, "utf8");
  const errors = [];
  let match;
  while ((match = importRegex.exec(content)) !== null) {
    const specifier = match[1];
    if (shouldCheckSpecifier(specifier) && specifier !== allowedSpecifier) {
      errors.push(specifier);
    }
  }
  return errors;
}

const sourceFiles = walk(projectRoot);
const findings = [];

for (const filePath of sourceFiles) {
  const invalidSpecifiers = validateFile(filePath);
  if (invalidSpecifiers.length > 0) {
    findings.push({
      filePath: path.relative(projectRoot, filePath),
      specifiers: [...new Set(invalidSpecifiers)],
    });
  }
}

if (findings.length > 0) {
  console.error("\nInvalid NexaJS import found. Use exactly \"NexaJS\".\n");
  for (const item of findings) {
    console.error(`- ${item.filePath}: ${item.specifiers.join(", ")}`);
  }
  console.error("\nExample valid import:\nimport { View } from \"NexaJS\";\n");
  process.exit(1);
}

console.log('NexaJS import validation passed: only "NexaJS" is used.');
