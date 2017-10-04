<?php
/**
 * Copyright (C) 2010 Arnoud Roques
 * Copyright (C) 2011 Pieter J. Kersten
 * Copyright (C) 2016-2017 Wolfgang Fahl
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */
use MediaWiki\Logger\LoggerFactory;

/**
 * the Plant UML extension main class
 */
class PlantUML {

    /**
     * Register this extension with the WikiText parser.
     * The first parameter is the name of the new tag. In this case the
     * tag <uml> ... </uml>. The second parameter is the callback function
     * for processing the text between the tags.
     */
    public static function onParserFirstCallInit( &$parser ) {
        $puml = new self();
        $parser->setHook( 'uml', [ $puml, 'renderUML' ] );
        return true;
    }

    /**
     * Clean the image folder when required
     */
    public static function onPageContentSave( &$wikiPage, &$user, &$content, &$summary,
        $isMinor, $isWatch, $section, &$flags, &$status ) {

        $puml = new self();
        $puml->cleanImages();
        return true;
    }

	/** 
	 * construct me
	 */
	function __construct() {
		$this->logger = LoggerFactory::getInstance( 'PlantUML' );
                $this->debug = $this->getProperty( 'Debug' );
		if ($this->debug) {
			$this->logger->debug("PlantUML constructed");
		}
	}

    /**
    * This call back function renders UML with PlantUML based on the information provided by $input.
    * @param format
    *   if set the use the given format - it may be png or svg
    */
    function renderUML( $input, $args, Parser $parser, PPFrame $frame ) {
        if (isset($args["format"])) {
            $format=$args["format"];
            $format=$parser->recursiveTagParse( $format, $frame );
        } else {
            $format=$this->getProperty( 'Format' );
        }
        $image = $this->getImage($input, $args, $parser);

	// if there is no image
        if ($image['src'] == false) {
            $text = "[An error occured in PlantUML extension: '".implode(",",$image)."']";
        } else {
            if ($format == 'svg') {
                $text = $this->renderSVG($image);
            } else if ($format == 'png') {
                $text = $this->renderPNG($image);
            } else {
                $text ="<span style='color:red'>PlantUML extension error - unknown format: ".$format."</span>";
            }
        }
        return $text;
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
     * 'mapid': the unique id for the rendered image map , useable for further
     *            HTML-rendering.
     */
    function getImage($PlantUML_Source, $argv, $parser=null) {
        // Compute hash
	$title=$this->getPageTitle($parser);
        $title_hash = md5($title);
        $formula_hash = md5($PlantUML_Source);

        $filename_prefix = 'uml-'.$title_hash."-".$formula_hash;
        $dirname = $this->getUploadDirectory();
        $full_path_prefix = $dirname."/".$filename_prefix;
        $imgFile = $dirname."/".$filename_prefix.".".$this->getProperty('Format');
        $mapFile = $dirname."/".$filename_prefix.".map";
        // setup the result map
        $result = array(
            'title' => $title, 'mapid' => $formula_hash, 'src' => false, 'map' => '', 'file' => ''
        );
        // Check cache. When found, reuse it. When not, generate image.
        // Honor the redraw tag as found in <uml redraw>
        if (is_file($imgFile) and is_file($mapFile) and !array_key_exists('redraw', $argv) ) {
            $result['file'] = $imgFile;
            $result['mapfile'] = $mapFile;
        } else {
            if ($this->getProperty( 'UseCloud' )) {
                $result['file'] = $this->renderPlantUML_cloud($PlantUML_Source, $imgFile);
                $result['mapfile'] = $this->renderPlantUML_cloud_map($PlantUML_Source, $mapFile);
            } else {
                $result['file'] = $this->renderPlantUML($PlantUML_Source, $imgFile, $dirname, $filename_prefix);
            }
        }
        // do we have a mapfile?
        if (isset($result['mapfile'])) {
            if (is_file($result['mapfile'])) {
                // map file is temporary data - read it and delete it.
                $fp = fopen($result['mapfile'],'r');
                $fsize=filesize($result['mapfile']);
                if ($fsize>0)
                  $image_map_data = fread($fp, $fsize);
                fclose($fp);
                if ($fsize>0) {
                  // Replace generic ids with unique ids: first two ".." fields.
                  $result['map'] = preg_replace('/"[^"]*"/', "\"{$result['mapid']}\"", $image_map_data, 2);
                }
            }
        }
        if ($result['file']) {
            $result['src'] = $this->getUploadPath()."/".basename($result['file']);
            if ((!$this->getProperty( 'UseCloud' )) && $this->getProperty('Format') == 'png') {
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

    /**
    * Clean the image folder when required
    */
    function cleanImages($parser=null) {
        $title_hash = md5($this->getPageTitle($parser));
        $path = $this->getUploadDirectory()."/uml-".$title_hash."-*.{svg,png,cmapx}";
        $files = glob($path, GLOB_BRACE);
        foreach ($files as $filename) {
            unlink($filename);
        }
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
        // Allow ditaa and graphviz first-line directives and accents (french, ..)
        // $string .= preg_replace("/^\r?\n/", "", utf8_decode($PlantUML_Source), 1) . "\n";
        $string.=$PlantUML_Source;
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
        $PlantUML_document = $this->wrap_formula($PlantUML_Source);

        // create temporary uml text file
        $umlFile = $dirname."/".$filename_prefix.".uml";
        $fp = fopen($umlFile,"wb");
        $w  = fputs($fp,mb_convert_encoding($PlantUML_document,'UTF-8'));
        fclose($fp);

        // Lauch PlantUML
        if ($this->getProperty( 'Format' ) == 'svg') {
            $typestr = ' -tsvg';
        } else {
            $typestr = '';
        }

        $jarFile = $this->getProperty( 'JarFile' );
        if (!is_file($jarFile) )
            $jarFile = dirname(__FILE__).'/'.$jarFile;

        $command = "java -jar ".$jarFile." ".
                "{$typestr} -charset UTF-8 -o \"{$dirname}\" \"{$umlFile}\"";

	// execute the java command
        exec($command,$ouput,$status_code);
	if ($this->debug) {
		$this->logger->debug("command '".$command."' returned status code=".$status_code."");
        }

	if ($this->debug) {
		$this->logger->debug("image file '".$imgFile."'");
        }

        // Only return existing path names.
        if (is_file($imgFile)) {
            // Delete temporary uml text file 
            unlink($umlFile);
            return $imgFile;
        } else {
		if ($this->debug) {
                        $this->logger->debug("image file is missing");
			// keep temporary uml text file to check
                } else {
            		// Delete temporary uml text file
              		unlink($umlFile);
		}
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
        $img = $this->getProperty( 'CloudURI' ) . $this->getProperty( 'Format' ) .'/';
        $img .= $this->encodep($PlantUML_Source);

        // Copy images into the local cache
        // -------------------------------------------
        // patch by Boly31 to support proxies
        // see https://www.mediawiki.org/wiki/Extension_talk:PlantUML#PlantUML_with_a_dedicated_plantuml_server_behind_proxy
        $proxy = getenv("http_proxy");
        // default no proxy behavior
        if ($proxy == "") {
            copy($img, $imgFile);
        } else {
           $proxyOpt = "tcp://" . $proxy;

           $opts = array(
                'http'=>array
                (
                    'method'=>"GET",
                    'proxy' => $proxyOpt,
                    'request_fulluri' => true
                )
            );
           $context = stream_context_create($opts);

           $contentx = file_get_contents($img,false,$context);
           $openedfile = fopen($imgFile, "w");
           fwrite($openedfile, $contentx);
           fclose($openedfile);
        }
        // -------------------------------------------

        if (is_file($imgFile)) {
            return $imgFile;
        }

        return false;
    }


    function renderPlantUML_cloud_map($PlantUML_Source, $mapFile) {
        // Build URL that describes the image
        $map = $this->getProperty( 'CloudURI' ) . 'map/';
        $map .= $this->encodep($PlantUML_Source);

        // Copy images into the local cache
        copy($map, $mapFile);

        if (is_file($mapFile)) {
            return $mapFile;
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
	$title=$wgTitle;
        // Retrieving the title of a page is not that easy
        if (empty($wgTitle)) {
	    if ($parser!=null)	
            	$title = $parser->getTitle()->getFulltext();
        }
        return $title;
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


    /**
     * Return a property for plantuml using global, request or passed default
     */
    function getProperty( $name ) {
        global $wgRequest;
        $prefix="PlantUml";
        if ( $wgRequest->getText( $prefix.$name ) )        return $wgRequest->getText( $prefix.$name );
        if ( $wgRequest->getText( "amp;".$prefix.$name ) ) return $wgRequest->getText( "amp;".$prefix.$name ); // hack to handle ampersand entities in URL
        $config = ConfigFactory::getDefaultInstance()->makeConfig( 'PlantUml' );
        return $config->get( $prefix.$name );
    }

    /**
     * PHP API Client Code
     * See http://plantuml.sourceforge.net/codephp.html
     */
    function encodep($text) {
        $data = mb_convert_encoding($text, 'UTF-8', mb_detect_encoding($text));
        $compressed = gzdeflate($data, 9);
        return $this->encode64($compressed);
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
        $r .= $this->encode6bit($c1 & 0x3F);
        $r .= $this->encode6bit($c2 & 0x3F);
        $r .= $this->encode6bit($c3 & 0x3F);
        $r .= $this->encode6bit($c4 & 0x3F);
        return $r;
    }

    function encode64($c) {
        $str = "";
        $len = strlen($c);
        for ($i = 0; $i < $len; $i+=3) {
            if ($i+2==$len) {
                $str .= $this->append3bytes(ord(substr($c, $i, 1)), ord(substr($c, $i+1, 1)), 0);
            } else if ($i+1==$len) {
                $str .= $this->append3bytes(ord(substr($c, $i, 1)), 0, 0);
            } else {
                $str .= $this->append3bytes(ord(substr($c, $i, 1)), ord(substr($c, $i+1, 1)), ord(substr($c, $i+2, 1)));
            }
        }
        return $str;
    }
}
