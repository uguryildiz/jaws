<?php
/**
 * ImageMagick implementation for Jaws_Image
 *
 * @category   Image
 * @package    Core
 * @author     Ali Fazelzadeh <afz@php.net>
 * @copyright  2012 Jaws Development Group
 * @license    http://www.gnu.org/copyleft/lesser.html
 * @link       http://pear.php.net/package/Image_Transform
 */
class Jaws_Image_Imagick extends Jaws_Image
{
    /**
     * Class constructor
     */
    function Jaws_Image_Imagick()
    {
        if (!extension_loaded('imagick')) {
            return Jaws_Error::raiseError('Imagick library is not available.',
                                          __FUNCTION__);
        } else {
            $this->_supported_image_types = array(
                    'bmp'  => 'rw',
                    'gif'  => 'rw',
                    'ico'  => 'r',
                    'jp2'  => 'rw',
                    'jpc'  => 'rw',
                    'jpg'  => 'rw',
                    'png'  => 'rw',
                    'psd'  => 'rw',
                    'tiff' => 'rw',
                    'wbmp' => 'rw',
                    'xbm'  => 'rw');
        }
    }

    /**
     * Loads an image from file
     *
     * @param string    $image filename
     * @param boolean   $readonly readonly
     * @return mixed True or a Jaws_Error object on error
     * @access public
     */
    function load($filename, $readonly = false)
    {
        $result = parent::load($filename, $readonly);
        if (Jaws_Error::IsError($result)) {
            return $result;
        }

        if (!$readonly) {
            $this->_hImage = new Imagick();
            try {
                $this->_hImage->readImage($filename);
            } catch (ImagickException $error) {
                return Jaws_Error::raiseError('Could not load image: '. $error->getMessage(),
                                              __FUNCTION__);
            }
        }

        return true;
    }

    /**
     * Loads an image from raw data
     *
     * @param string    $data  image raw data
     * @param boolean   $readonly readonly
     * @return mixed True or a Jaws_Error object on error
     * @access public
     */
    function setData($data, $readonly = false)
    {
        $result = parent::setData($data, $readonly);
        if (Jaws_Error::IsError($result)) {
            return $result;
        }

        if (!$readonly) {
            $this->_hImage = new Imagick();
            try {
                $this->_hImage->readImageBlob($data);
            } catch (ImagickException $error) {
                return Jaws_Error::raiseError('Could not load image from string: '. $error->getMessage(),
                                              __FUNCTION__);
            }
        }

        return true;
    }

    /**
     * Returns the Imagick image object
     *
     * @return resource
     * @access public
     */
    function &getHandle()
    {
        return $this->_hImage;
    }

    /**
     * Resizes the image
     *
     * @param int   $new_w   New width
     * @param int   $new_h   New height
     * @param array $options Optional parameters(eg. 'scaleMethod': "pixel" or "smooth")
     * @return mixed True on success or Jaws_Error object on error
     * @access public
     */
    function resize($new_w, $new_h, $options = null)
    {
        $this->_parse_size_by_aspect_ratio($new_w, $new_h);
        $scaleMethod = $this->_getOption('scaleMethod', $options, 'smooth');
        $blur = ($scaleMethod == 'pixel') ? 0 : 1;
        try {
            $this->_hImage->resizeImage($new_w, $new_h, imagick::FILTER_UNDEFINED, $blur);
        } catch (ImagickException $error) {
            return Jaws_Error::raiseError('Could not resize image.',
                                          __FUNCTION__);
        }

        $this->_img_w = $new_w;
        $this->_img_h = $new_h;
        return true;
    }

    /**
     * Crops image by size and start coordinates
     *
     * @param int width Cropped image width
     * @param int height Cropped image height
     * @param int x X-coordinate to crop at
     * @param int y Y-coordinate to crop at
     * @return mixed True or a Jaws_Error object on error
     * @access public
     */
    function crop($width, $height, $x = 0, $y = 0)
    {
        // Sanity check
        if (!$this->_intersects($width, $height, $x, $y)) {
            return Jaws_Error::raiseError('Nothing to crop.',
                                          __FUNCTION__);
        }

        $x = min($this->_img_w, max(0, $x));
        $y = min($this->_img_h, max(0, $y));
        $width  = min($width,  $this->_img_w - $x);
        $height = min($height, $this->_img_h - $y);

        try {
            $this->_hImage->cropImage($width, $height, $x, $y);
        } catch (ImagickException $error) {
            return Jaws_Error::raiseError('Could not crop image.',
                                          __FUNCTION__);
        }

        $this->_img_w = $width;
        $this->_img_h = $height;
       return true;
    }

    /**
     * Rotates image by the given angle
     * Uses a fast rotation algorythm for custom angles or lines copy for multiple of 90 degrees
     *
     * @param int   $angle   Rotation angle
     * @param array $options array('canvasColor' => array(r ,g, b), named color or #rrggbb)
     * @author Pierre-Alain Joye
     * @return bool True on success or a false on error
     * @access public
     */
    function rotate($angle, $options = null)
    {
        if (($angle % 360) == 0) {
            return true;
        }

        $color_mask = $this->_getColor('canvasColor',
                                       $options,
                                       array(255, 255, 255));
        if (is_array($color_mask)) {
            $color_mask = $this->_colorarray2colorhex($color_mask);
        }

        $pixel = new ImagickPixel($color_mask);
        try {
            $this->_hImage->rotateImage($pixel, $angle);
        } catch (ImagickException $error) {
            return Jaws_Error::raiseError('Cannot create a new imagick image for the rotation: '. $error->getMessage(),
                                          __FUNCTION__);
        }

        $info = $this->_hImage->getImageGeometry();
        $this->_img_w = $info['width'];
        $this->_img_h = $info['height'];
        return true;
    }

    /**
     * Adjusts the image gamma
     *
     * @param float $gamma
     *
     * @return mixed True or a Jaws_Error on error
     * @access public
     **/
    function gamma($gamma = 1.0)
    {
        try {
            $this->_hImage->gammaImage($gamma);
        } catch (ImagickException $error) {
            return Jaws_Error::raiseError('Failed transformation: gamma().',
                                          __FUNCTION__);
        }

        return true;
    }

    /**
     * Horizontal mirroring
     *
     * @return mixed True or Jaws_Error on error
     * @access public
     **/
    function mirror()
    {
        try {
            $this->_hImage->flopImage();
        } catch (ImagickException $error) {
            return Jaws_Error::raiseError('Could not mirror the image.',
                                          __FUNCTION__);
        }

        return true;
    }

    /**
     * Vertical mirroring
     *
     * @return mixed True or Jaws_Error on error
     * @access public
     **/
    function flip()
    {
        try {
            $this->_hImage->flipImage();
        } catch (ImagickException $error) {
            return Jaws_Error::raiseError('Could not flip the image.',
                                          __FUNCTION__);
        }

        return true;
    }

    /**
     * Converts an image into grayscale colors
     *
     * @return mixed True or Jaws_Error on error
     **/
    function grayscale()
    {
        try {
            $this->_hImage->setImageType(Imagick::IMGTYPE_GRAYSCALE);
        } catch (ImagickException $error) {
            return Jaws_Error::raiseError('Failed transformation: grayscale().',
                                          __FUNCTION__);
        }

        return true;
    }

    /**
     * Saves the image to a file
     *
     * @param string $filename the name of the file to write to
     * @param string $type     the output format, default is the current used format
     * @param int    $quality  default is 75
     *
     * @return mixed True on success or Jaws_Error object on error
     * @access public
     */
    function save($filename = '', $type = '', $quality = null)
    {
        $options = (is_array($quality)) ? $quality : array();
        if (is_numeric($quality)) {
            $options['quality'] = $quality;
        }

        $quality = $this->_getOption('quality', $options, 75);
        try {
            $this->_hImage->setImageCompression($quality);
        } catch (ImagickException $error) {
            return Jaws_Error::raiseError('Could not set image compression.',
                                          __FUNCTION__);
        }

        $type = ($type == 'jpg')? 'jpeg' : $type;
        $type = strtolower(($type == '') ? $this->_itype : $type);

        try {
            $this->_hImage->setImageFormat($type);
        } catch (ImagickException $error) {
            return Jaws_Error::raiseError('Could not save image to file (conversion failed).',
                                          __FUNCTION__);
        }

        $filename = empty($filename)? $this->_ifname : $filename;
        try {
            $this->_hImage->writeImage($filename);
        } catch (ImagickException $error) {
            return Jaws_Error::raiseError('Could not save image to file: '. $error->getMessage(),
                                          __FUNCTION__);
        }

        return true;
    }

    /**
     * Displays image without saving and lose changes.
     * This method adds the Content-type HTTP header
     *
     * @param string $type     the output format, default is the current used format
     * @param int    $quality  default is 75
     * @param int    $expires  set Cache-Control and Expires of HTTP header
     *
     * @return mixed True on success or Jaws_Error object on error
     * @access public
     */
    function display($type = '', $quality = null, $expires = 0)
    {
        if ($this->_readonly) {
            $result = parent::display($type, $quality, $expires);
            return $result;
        }

        $options = (is_array($quality)) ? $quality : array();
        if (is_numeric($quality)) {
            $options['quality'] = $quality;
        }

        $quality = $this->_getOption('quality', $options, 75);
        try {
            $this->_hImage->setImageCompression($quality);
        } catch (ImagickException $error) {
            return Jaws_Error::raiseError('Could not set image compression.',
                                          __FUNCTION__);
        }

        $type = ($type == 'jpg')? 'jpeg' : $type;
        $type = strtolower(($type == '') ? $this->_itype : $type);
        $type = empty($type)? 'png' : $type;

        try {
            $this->_hImage->setImageFormat($type);
        } catch (ImagickException $error) {
            return Jaws_Error::raiseError('Could not save image to file (conversion failed).',
                                          __FUNCTION__);
        }

        try {
            $result = $this->_hImage->getImageBlob();
        } catch (ImagickException $error) {
            return Jaws_Error::raiseError('Could not display image.',
                                          __FUNCTION__);
        }

        if (!empty($expires)) {
            header("Cache-Control: max-age=". $expires);
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $expires). ' GMT');
        }

        header('Content-type: ' . image_type_to_mime_type($this->get_image_extension_to_type($type)));
        echo $result;
        $this->free();
        return true;
    }

    /**
     * Destroys image handle
     *
     * @access public
     */
    function free()
    {
        if (isset($this->_hImage)) {
            $this->_hImage->destroy();
        }
 
        parent::free();
    }

}