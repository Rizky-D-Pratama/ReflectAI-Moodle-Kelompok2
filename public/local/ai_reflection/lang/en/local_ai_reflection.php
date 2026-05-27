<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'AI Reflection';
// Capabilities
$string['ai_reflection:viewresults'] = 'View AI Reflection results';
$string['ai_reflection:addteachernote'] = 'Add teacher note to AI Reflection';
// Settings
$string['setting_ollamaurl'] = 'Ollama URL';
$string['setting_ollamaurl_desc'] = 'URL of the Ollama server used to process AI reflections. Example: http://localhost:11434';
$string['setting_ollamamodel'] = 'Ollama Model Name';
$string['setting_ollamamodel_desc'] = 'Name of the Ollama model to use. Example: gemma3, llama3, mistral';
$string['setting_ollamatimeout'] = 'Request Timeout (seconds)';
$string['setting_ollamatimeout_desc'] = 'Maximum time to wait for a response from Ollama. Increase this value if timeouts occur when processing large files.';
$string['setting_ollamabatchsize'] = 'Image Batch Size';
$string['setting_ollamabatchsize_desc'] = 'Number of images sent to Ollama per request. Decrease this value if timeouts occur when processing images.';