Hi, welcome to the [PlantUML](http://plantuml.com/) plugin for [MediaWiki](https://www.mediawiki.org/wiki/MediaWiki).
The full project page for this plugin can be found on 

   http://www.mediawiki.org/wiki/Extension:PlantUML
   

# Prerequisites
A [Java](https://www.java.com/en/) installation. Can also be [OpenJDK](http://openjdk.java.net/)
[GraphViz extension](https://www.mediawiki.org/wiki/Extension:GraphViz)

# Installation

## With Composer
[Composer support is in preparation] (https://www.mediawiki.org/wiki/User_talk:Legoktm#Adding_Extension_PlantUML_to_packagist)


## Without Composer

1. Go to the extensions folder of your MediaWiki installation. On RedHat and
   derivates this will be /usr/share/mediawiki/extensions.
   `cd /usr/share/mediawiki/extensions`

2. Create a new subdirectory PlantUML in this folder and move to this
   directory.
   `mkdir PlantUML && cd PlantUML`
   
3. Move the PlantUML.php file into the PlantUML directory.
   `mv <downloaddir>/PlantUML.php`.

4. Choose your usage style. You can either process images locally (on the
   server where MediaWiki was installed) or in the cloud. The local version
   supports SVG-images and embedded urls, at the cost of local processing.
   The cloud version is lightweight for your server, but does not support
   embedded urls (yet) and is (still) stuck with PNG-images.
   Default local processing is expected. If you want to use the cloud,
   please edit the PlantUML.php file and change $usecloud to true.

5. When using local processing: get the plantuml.jar from SourceForge

   `wget https://downloads.sourceforge.net/project/plantuml/plantuml.jar`
   
6. (Optional) Edit PlantUML.php and change the variable $plantumlImagetype
   to your preference. Mind that SVG produces the superior graphics, but that
   only PNG-images and image maps are "rock solid". If you use the cloud, it
   will always generate PNG images. Local processing defaults to SVG.

7. (Optional) Adapt the getUploadPath and getUploadDirectory to your
   preference if you want these different from MediaWiki's standard settings.
   Mind that these directories must be writeable by the system user who runs
   MediaWiki.

8. Put the following line near the end of your LocalSettings.php in
   MediaWiki's root folder to include the extension:
   
   `require_once('extensions/PlantUML/PlantUML.php');`

9. When using the cloud, make sure that httpd can submit HTTP-requests.

10. Reload http
   `service httpd graceful

11. Enjoy!

# Issues
If you have suggestions or remarks, please [file an issue](https://github.com/pjkersten/PlantUML/issues)!
