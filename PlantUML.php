<?php
/**
 * Parser hook extension adds a <uml> tag to wiki markup for rendering UML
 * diagrams within a wiki page using PlantUML.
 *
 * Installation:
 *  1. Create a subdirectory PlanetUML in your extensions folder.
 *  2. Copy this file and the planetuml.jar file into this folder.
 *  3. Change the variable $planetImagetype to your preference.
 *  4. Adapt the getUploadPath and getUploadDirectory to your preference
 *     if you want these different from MediaWiki's standard settings.
 *  5. Put the following line near the end of your LocalSettings.php in
 *     MediaWiki's root folder to include the extension:
 *
 * require_once('extensions/PlantUML/PlantUML.php');
 *
 *  6. Enjoy!
 *
 * CHANGES:
 *   Version 0.1: Roques, A.
 *     - First version.
 *   Version 0.2:  Kersten, Pieter J., April 12, 2011
 *     - Adapt install to "standard" mediawiki extension behavior.
 *     - Add support for embedded url's in graphics.
 *     - Add support for SVG-files.
 *     - Add plantuml class attribute to resulting images for CSS-styling.
 *     - Extend and update documentation.
 *     - Expand tabwidth to 4 spaces everywhere.
 *   Version 0.3: Kersten, Pieter J., May 5, 2011
 *     - Add getPageTitle() function from plugin page
 */

/**
 * You can change $plantumlJar to match your config if it is not installed
 * as advised above. Use quote if the path contains spaces characters.
 * You can use the basename if you put the jar in the same folder as this
 * file.
 *
 * Example:
 *   $plantumlJar  = "\"d:/Program Files/PlantUML/plantuml.jar\"";
 *
 */
$plantumlJar = 'plantuml.jar'; // Test version for embedded url's

/**
 * Change $plantumlImagetype to either 'svg' or 'png'. Although SVG
 * delivers superior graphics over PNG, not all environments love it.
 * PNG-images and image maps always work.
 */
$plantumlImagetype = 'svg';
 
/**
 * You can change the result of the getUploadDirectory() and getUploadPath()
 * if you want to put generated images somewhere else.
 * By default, it equals the upload directory.
 */
function getUploadDirectory() {
    global $wgUploadDirectory;
    return $wgUploadDirectory;
}
 
function getUploadPath() {
    global $wgUploadPath;
    return $wgUploadPath;
}
 
/*****************************************************************************
 * Don't change from here, unless you know what you're doing. If you do,
 * please consider sharing your changes and motivation with us.
 */

// Make sure we are being called properly
if( !defined( 'MEDIAWIKI' ) ) {
    echo( "This file is an extension to the MediaWiki software and cannot be used standalone.\n" );
    die( -1 );
}
 
// Avoid unstubbing $wgParser too early on modern (1.12+) MW versions, as per r35980
if ( defined( 'MW_SUPPORTS_PARSERFIRSTCALLINIT' ) ) {
    $wgHooks['ParserFirstCallInit'][] = 'wfPlantUMLExtension';
    $wgHooks['ArticleSave'][] = 'cleanImages';
} else {
    $wgExtensionFunctions[] = 'wfPlantUMLExtension';
}

// Auto locate jar file
if (!is_file($plantumlJar)) {
    $plantumlJar = dirname(__FILE__).'/'.$plantumlJar;
}

// Install extension
$wgExtensionCredits['parserhook'][] = array(
    'name' => 'UML',
    'version' => '0.2',
    'author' => 'Roques A., Kersten Pieter J.',
    'url' => 'http://www.mediawiki.org/wiki/Extension:PlantUML',
    'description' => 'Renders a UML model from text using PlantUML.'
);
 
/**
 * Clean the image folder when required
 */
function cleanImages() {
    $title_hash = md5(getPageTitle());
    $path = getUploadDirectory()."/uml-".$title_hash."-*.{svg,png,cmapx}";
    $files = glob($path, GLOB_BRACE);
    foreach ($files as $filename) {
        unlink($filename);
    }
    return true;
}
 
/** 
 * Register this extension with the WikiText parser.
 * The first parameter is the name of the new tag. In this case the
 * tag <uml> ... </uml>. The second parameter is the callback function
 * for processing the text between the tags.
 */
function wfPlantUMLExtension() {
    global $wgParser;
    $wgParser->setHook( 'uml', 'renderUML' );
    return true;
}
 
/**
 * wraps a minimalistic PlantUML document around the formula and returns a string
 * containing the whole document as string.
 *
 * @param string model in PlantUML format
 * @returns minimalistic PlantUML document containing the given model
 */
function wrap_formula($PlantUML_Source) {
    $string  = "@startuml\n";
    $string .= "$PlantUML_Source\n";
    $string .= "@enduml";
 
    return $string;
}
 
/**
 * Renders a PlantUML model by the using the following method:
 *  - write the formula into a wrapped plantuml file
 *  - Use a filename a md5 hash of the uml source
 *  - Launch PlantUML to create the PNG file into the picture cache directory
 *
 * @param string PlantUML model
 * @param $dirname: directory of generated files
 * @param $filename_prefix: unique prefix for $dirname
 *
 * @returns the full path location of the rendered picture when
 *          successfull, false otherwise
 */
function renderPlantUML($PlantUML_Source, $dirname, $filename_prefix) {
    global $plantumlJar, $plantumlImagetype;
 
    $imgFile = $dirname."/".$filename_prefix.".$plantumlImagetype";
    if (is_file($imgFile)) {
        return $imgFile;
    }

    $PlantUML_document = wrap_formula($PlantUML_Source);
 
    // create temporary uml text file
    $umlFile = $dirname."/".$filename_prefix.".uml";
    $fp = fopen($umlFile,"w+");
    $w = fputs($fp,$PlantUML_document);
    fclose($fp);
 
    // Lauch PlantUML
    if ($plantumlImagetype == 'svg') {
        $typestr = ' -tsvg';
    } else {
        $typestr = '';
    }
    $command = "java -jar ".$plantumlJar.
               "{$typestr} -o \"{$dirname}\" \"{$umlFile}\"";
 
    $status_code = exec($command);
 
    // Delete temporary uml text file
    unlink($umlFile);
 
    // Only return existing path names.
    if (is_file($imgFile)) {
        return $imgFile;
    }
 
    return false;
}

/**
 * Get a title for this page.
 * @returns title
 */
function getPageTitle() {
    global $wgArticle;
    global $wgTitle;
    // Retrieving the title of a page is not that easy
    if (empty($wgTitle)) {
        $title = $wgArticle->getTitle()->getText();
        return $title;
    }
    return $wgTitle;
}

/**
 * Tries to match the PlantUML given as argument against the cache. 
 * If the picture has not been rendered before, it'll
 * try to render the PlantUML and drop it in the picture cache directory.
 * Embedded links will be expanded into a image map file with the same
 * name, but extension ".cmapx". When found, it will be included in the
 * results.
 *
 * @param string model in been format
 * @returns an array with four elements:
 *   'src':   the webserver based URL to a picture which contains the
 *            requested PlantUML model. If anything fails, this value is
 *            false.
 *   'file':  the full pathname to the file containing the image map data
 *            when present. When no map data is present, this value is empty.
 *   'map':   the rendered HTML-fragment for an image map. Empty when not
 *            needed.
 *   'mapid': the unique id for the rendered image map , useable for further
 *            HTML-rendering.
 */
function getImage($PlantUML_Source) {
    global $plantumlImagetype;

    // Compute hash
    $title_hash = md5(getPageTitle());
    $formula_hash = md5($PlantUML_Source);

    $filename_prefix = 'uml-'.$title_hash."-".$formula_hash;
    $dirname = getUploadDirectory();
    $full_path_prefix = $dirname."/".$filename_prefix;
    $result = array(
        'mapid' => $formula_hash, 'src' => false, 'map' => '',
        'file' => renderPlantUML($PlantUML_Source, $dirname, $filename_prefix)
    );
    if ($result['file']) {
        $result['src'] = getUploadPath()."/".basename($result['file']);
        if ($plantumlImagetype == 'png') {
            $map_filename = $full_path_prefix.".cmapx";
            if (is_file($map_filename)) {
                // map file is temporary data - read it and delete it.
                $fp = fopen($map_filename,'r');
                $image_map_data = fread($fp, filesize($map_filename));
                fclose($fp);
                //unlink($map_filename);
                // Replace generic ids with unique ids: first two ".." fields.
                $result['map'] = preg_replace('/"[^"]*"/', "\"{$result['mapid']}\"", $image_map_data, 2);
            }
        }
    }
    return $result;
}

/**
 * renderSVG wraps a SVG-image in a correctly sized <object> 
 *
 * @param $image: the array of image data generated by getImage()
 * @returns: the rendered HTML string for the svg image.
 */
function renderSVG($image) {
    // In essence, we don't have to embed the svg image ourselves, but it is
    // imperative that we know the size of the object, otherwise it will clip.
    $fp = fopen($image['file'],'r');
    $data = fread($fp, 200);
    fclose($fp);
    #$data = str_replace("\n","",$data);
    $parts = explode('><', $data);
    /*
    $dimensions = preg_replace('/(.*)style="width:(\d+);height:(\d+);"(.*)/', '${2} ${3}', $parts[1]);
    $data = explode(' ', $dimensions);
    $width = trim($data[0]);
    $height = trim($data[1]);
    */
    $dimensions = preg_replace('/.*style="width:(\d+);height:(\d+);".*/', 'width=${1} height=${2}', $parts[1]);

    #    'width="'.$width.'"'.' height="'.$height.'">'.
    return '<object class="planetuml" type="image/svg+xml" data="'.$image['src'].'" '.
           $dimensions.
           '>Your browser has no SVG support. '.
           'Please install <a href="http://www.adobe.com/svg/viewer/install/">Adobe '.
           'SVG Viewer</a> plugin (for Internet Explorer) or use <a href="'.
           'http://www.getfirefox.com/">Firefox</a>, <a href="http://www.opera.com/">'.
           'Opera</a> or <a href="http://www.apple.com/safari/download/">Safari</a> '.
           'instead.'.
         '</object>';
}

/**
 * renderSVG returns the correct HTML for the image in $image 
 *
 * @param $image: the array of image data generated by getImage()
 * @returns: the rendered HTML string for the svg image.
 */
function renderPNG($image) {
    if ($image['map']) {
        $usemap = ' usemap="#'.$image['mapid'].'"';
    } else {
        $usemap = '';
    }
    return "<img class=\"planetuml\" src=\"{$image['src']}\"$usemap>{$image['map']}";
}

# The callback function for converting the input text to HTML output
function renderUML( $input, $argv ) {
    global $plantumlImagetype;
    $image = getImage($input);
 
    if ($image['src'] == false) {
        $text = "[An error occured in PlantUML extension]";
    } else {
        if ($plantumlImagetype == 'svg') {
            $text = renderSVG($image);
        } else {
            $text = renderPNG($image);
        }
    }
    return $text;
}

