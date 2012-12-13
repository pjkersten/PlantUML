<?php
/**
 * Parser hook extension adds a <uml> tag to wiki markup for rendering UML
 * diagrams within a wiki page using PlantUML.
 *
 * Installation:
 *  1. Create a subdirectory PlantUML in your extensions folder.
 *  2. Copy this file and (optionally) the plantuml.jar file into this folder.
 *  3. Change the variable $plantImagetype to your preference. Mind that SVG
 *     is only supported if you use the jar file locally.
 *  4. Choose the right setting for $usecloud. See comments for that.
 *  5. Adapt the getUploadPath and getUploadDirectory to your preference
 *     if you want these different from MediaWiki's standard settings.
 *  6. Put the following line near the end of your LocalSettings.php in
 *     MediaWiki's root folder to include the extension:
 *
 * require_once('extensions/PlantUML/PlantUML.php');
 *
 *  7. Enjoy!
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
 *   Version 0.4: Kersten, Pieter J., May 6, 2011
 *     - Include cloud API to produce a single plugin.
 *     - Drop separate cloud version.
 */

/**
 * You can choose between cloud usage and local usage. The cloud version is
 * light-weight, but limited in functionality. There are no embedded URLs and
 * there are no SVG graphics yet. The local version supports both SVG-graphics
 * and embedded URLs at the cost of local processing power. Default is to use
 * the local version.
 * Set the $usecloud to true in order to use the cloud.
 * Mind that php must be able to open remote urls, so either check
 * php.ini to contain 'allow_url_fopen = On' or add 
 *    php_value allow_url_fopen On
 * to either .htaccess or your (vhost) config file (Apache).
 * Mind that httpd must be allowed to fire http-requests as well, and some
 * environments (selinux notably) may prevent that in default install.
 */
$usecloud = false;

/**
 * You can change $plantumlJar to match your config if it is not installed
 * as advised above. Use quote if the path contains space characters.
 * You can use the basename if you put the jar in the same folder as this
 * file. Will be ignored when using the cloud.
 *
 * Example:
 *   $plantumlJar  = "\"d:/Program Files/PlantUML/plantuml.jar\"";
 *
 */
$plantumlJar = 'plantuml.jar';

/**
 * Change $plantumlImagetype to either 'svg' or 'png'. Although SVG
 * delivers superior graphics over PNG, not all environments love it.
 * PNG-images and image maps always work. Usage of the cloud will reset
 * this to 'png'.
 */
$plantumlImagetype = 'svg';
 
/**
 * You can change the result of the getUploadDirectory() and getUploadPath()
 * if you want to put generated images somewhere else.
 * By default, it equals the upload directory. Mind that the process creating
 * the images must be able to create new files there.
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

// Auto locate jar file for local usage
if (!$usecloud) {
    if (!is_file($plantumlJar)) {
        $plantumlJar = dirname(__FILE__).'/'.$plantumlJar;
    }
    if (!is_file($plantumlJar)) {
        $usecloud = true;
    }
}
// Cloud version does not support SVG images (yet)
if ($usecloud) {
    $plantumlImagetype = 'png';
}

// Install extension
$wgExtensionCredits['parserhook'][] = array(
    'name' => 'UML',
    'version' => '0.4',
    'author' => 'Roques A., Kersten Pieter J.',
    'url' => 'http://www.mediawiki.org/wiki/Extension:PlantUML',
    'description' => 'Renders a UML model from text using PlantUML.'
);
 
/**
 * Clean the image folder when required
 */
function cleanImages($parser=null) {
    $title_hash = md5(getPageTitle($parser));
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
function wfPlantUMLExtension($parser) {
    $parser->setHook( 'uml', 'renderUML' );

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
    // utf8 encode allows handling accents (french, etc.)
    $string .= utf8_decode("$PlantUML_Source\n");
    $string .= "@enduml";
 
    return $string;
}
 
/**
 * Renders a PlantUML model by the using the following method:
 *  - write the formula into a wrapped plantuml file
 *  - Use a filename a md5 hash of the uml source
 *  - Launch PlantUML to create the PNG file into the picture cache directory
 *
 * @param string PlantUML_Source
 * @param string imgFile: full path of to-be-generated image file.
 * @param string dirname: directory of generated files
 * @param string filename_prefix: unique prefix for $dirname
 *
 * @returns the full path location of the rendered picture when
 *          successfull, false otherwise
 */
function renderPlantUML($PlantUML_Source, $imgFile, $dirname, $filename_prefix) {
    global $plantumlJar, $plantumlImagetype;
 
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
 * Renders a PlantUML model by the using the following method:
 *  - Encode the source like explained here: http://plantuml.sourceforge.net/codephp.html
 *  - Use as filename a md5 hash of the uml source
 *  - Copy the image generated by http://www.plantuml.com in the upload directory
 *
 * @param string PlantUML_Source: the source of the UML image
 * @param string imgFile: full path of to-be-generated image file.
 * @returns true if the picture has been successfully saved to the picture
 *          cache directory
 */
function renderPlantUML_cloud($PlantUML_Source, $imgFile) {
    // Build URL that describes the image
    $img = "http://www.plantuml.com/plantuml/img/"; 
    $img .= encodep($PlantUML_Source); 
 
    // Copy images into the local cache 
    copy($img, $imgFile);
 
    if (is_file($imgFile)) {
        return $imgFile;
    }
 
    return false;
}
 
/**
 * Get a title for this page.
 * @returns title
 */
function getPageTitle($parser) {
    global $wgArticle;
    global $wgTitle;
    // Retrieving the title of a page is not that easy
    if (empty($wgTitle)) {
        $title = $parser->getTitle()->getFulltext();
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
function getImage($PlantUML_Source, $argv, $parser=null) {
    global $plantumlImagetype;
    global $usecloud;

    // Compute hash
    $title_hash = md5(getPageTitle($parser));
    $formula_hash = md5($PlantUML_Source);

    $filename_prefix = 'uml-'.$title_hash."-".$formula_hash;
    $dirname = getUploadDirectory();
    $full_path_prefix = $dirname."/".$filename_prefix;
    $result = array(
        'mapid' => $formula_hash, 'src' => false, 'map' => '', 'file' => ''
    );
    $imgFile = $dirname."/".$filename_prefix.".$plantumlImagetype";
    // Check cache. When found, reuse it. When not, generate image.
    // Honor the redraw tag as found in <uml redraw>
    if (is_file($imgFile) and not array_key_exists('redraw', $argv) ) {
        $result['file'] = $imgFile;
    } else {
        if ($usecloud) {
            $result['file'] = renderPlantUML_cloud($PlantUML_Source, $imgFile);
        } else {
            $result['file'] = renderPlantUML($PlantUML_Source, $imgFile, $dirname, $filename_prefix);
        }
    }
    if ($result['file']) {
        $result['src'] = getUploadPath()."/".basename($result['file']);
        if ((!$usecloud) && $plantumlImagetype == 'png') {
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
    $parts = explode('><', $data);
    $dimensions = preg_replace('/.*style="width:(\d+);height:(\d+);".*/', 'width=${1} height=${2}', $parts[1]);

    #    'width="'.$width.'"'.' height="'.$height.'">'.
    return '<object class="plantuml" type="image/svg+xml" data="'.$image['src'].'" '.
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
 * renderPNG returns the correct HTML for the image in $image 
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
    return "<img class=\"plantuml\" src=\"{$image['src']}\"$usemap>{$image['map']}";
}

# The callback function for converting the input text to HTML output
function renderUML( $input, $argv, $parser=null ) {
    global $plantumlImagetype;
    $image = getImage($input, $argv, $parser);
 
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

/**
 * PHP API Client Code
 * See http://plantuml.sourceforge.net/codephp.html
 */
function encodep($text) { 
    $data = mb_convert_encoding($text, 'UTF-8', mb_detect_encoding($text));
    $compressed = gzdeflate($data, 9); 
    return encode64($compressed); 
} 
 
function encode6bit($b) { 
    if ($b < 10) { 
        return chr(48 + $b); 
    } 
    $b -= 10; 
    if ($b < 26) { 
        return chr(65 + $b); 
    } 
    $b -= 26; 
    if ($b < 26) { 
        return chr(97 + $b); 
    } 
    $b -= 26; 
    if ($b == 0) { 
        return '-'; 
    } 
    if ($b == 1) { 
        return '_'; 
    } 
    return '?'; 
} 
 
function append3bytes($b1, $b2, $b3) { 
    $c1 = $b1 >> 2; 
    $c2 = (($b1 & 0x3) << 4) | ($b2 >> 4); 
    $c3 = (($b2 & 0xF) << 2) | ($b3 >> 6); 
    $c4 = $b3 & 0x3F; 
    $r = ""; 
    $r .= encode6bit($c1 & 0x3F); 
    $r .= encode6bit($c2 & 0x3F); 
    $r .= encode6bit($c3 & 0x3F); 
    $r .= encode6bit($c4 & 0x3F); 
    return $r; 
} 
 
function encode64($c) { 
    $str = ""; 
    $len = strlen($c); 
    for ($i = 0; $i < $len; $i+=3) { 
        if ($i+2==$len) { 
            $str .= append3bytes(ord(substr($c, $i, 1)), ord(substr($c, $i+1, 1)), 0); 
        } else if ($i+1==$len) { 
            $str .= append3bytes(ord(substr($c, $i, 1)), 0, 0); 
        } else { 
            $str .= append3bytes(ord(substr($c, $i, 1)), ord(substr($c, $i+1, 1)), ord(substr($c, $i+2, 1)));
        } 
    } 
    return $str; 
}
