<?php

libxml_use_internal_errors(true);

$model = implode(',', array_fill(0, 50_000, 'a'));
$xml = '<!DOCTYPE root [<!ELEMENT root ('.$model.')><!ELEMENT a EMPTY><!ELEMENT wrong EMPTY>]><root><wrong/></root>';
$document = new DOMDocument;
$document->validateOnParse = true;
$loaded = $document->loadXML($xml);
$errors = libxml_get_errors();
$message = trim($errors[0]->message ?? '');

if (! $loaded || count($errors) !== 1 || strlen($message) > 6_000 || ! str_contains($message, '...')) {
    fwrite(STDERR, "libxml2 DTD diagnostic was not safely bounded.\n");
    exit(1);
}

printf("PASS: libxml2 bounded DTD diagnostic (%d bytes).\n", strlen($message));
