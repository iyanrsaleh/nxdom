<?php
$html = file_get_contents(__DIR__ . '/../../templates/theme/docs/platform/javascript/menu.html');
libxml_use_internal_errors(true);
$dom = new DOMDocument();
$ok = $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
var_dump($ok);
echo 'errors: ' . count(libxml_get_errors()) . PHP_EOL;
libxml_clear_errors();
$xp = new DOMXPath($dom);
$nodes = $xp->query('//a[starts-with(@href, "/docs/platform/javascript")]');
echo 'nodes: ' . ($nodes ? $nodes->length : 0) . PHP_EOL;
