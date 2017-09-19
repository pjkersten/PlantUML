<?php

if ( function_exists( 'wfLoadExtension' ) ) {
    wfLoadExtension( 'PlantUML' );
    // Keep i18n globals so mergeMessageFileList.php doesn't break
    $wgMessagesDirs['PlantUML'] = __DIR__ . '/i18n';
    return true;
} else {
    die( 'This version of the TemplateData extension requires MediaWiki 1.25+' );
}
