<?php
// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

// +-----------------------------------------------------------------------+
// |                           Image Interface                             |
// +-----------------------------------------------------------------------+

// Define all necessary methods for image class
interface imageInterface
{
  function get_width();

  function get_height();

  function set_compression_quality($quality);

  function crop($width, $height, $x, $y);

  function strip();

  function rotate($rotation);

  function resize($width, $height);

  function sharpen($amount);

  function compose($overlay, $x, $y, $opacity);

  function write($destination_filepath);
}

// +-----------------------------------------------------------------------+
// |                          Main Image Class                             |
// +-----------------------------------------------------------------------+

class pwg_image
{
  var $image;
  var $library = '';
  var $source_filepath = '';

  function __construct($source_filepath, $library=null)
  {
    global $conf;
    $this->source_filepath = $source_filepath;

    trigger_notify('load_image_library', array(&$this) );

    $extension = strtolower(get_extension($source_filepath));

    if (!in_array($extension, $conf['picture_ext']))
    {
      die('[Image] unsupported file extension');
    }

    if (!($this->library = self::get_library($library, $extension)))
    {
      die('No image library available on your server.');
    }

    $class = 'image_'.$this->library;
    $this->image = new $class($source_filepath);
  }

  // Unknown methods will be redirected to image object
  function __call($method, $arguments)
  {
    return call_user_func_array(array($this->image, $method), $arguments);
  }

  // Piwigo resize function
  function pwg_resize($destination_filepath, $max_width, $max_height, $quality, $automatic_rotation=true, $strip_metadata=false, $crop=false, $follow_orientation=true)
  {
    $starttime = get_moment();

    // width/height
    $source_width  = $this->image->get_width();
    $source_height = $this->image->get_height();

    $rotation = null;
    if ($automatic_rotation)
    {
      $rotation = self::get_rotation_angle($this->source_filepath);
    }
    $resize_dimensions = self::get_resize_dimensions($source_width, $source_height, $max_width, $max_height, $rotation, $crop, $follow_orientation);

    // testing on height is useless in theory: if width is unchanged, there
    // should be no resize, because width/height ratio is not modified.
    if ($resize_dimensions['width'] == $source_width and $resize_dimensions['height'] == $source_height)
    {
      // the image doesn't need any resize! We just copy it to the destination
      copy($this->source_filepath, $destination_filepath);
      return $this->get_resize_result($destination_filepath, $resize_dimensions['width'], $resize_dimensions['height'], $starttime);
    }

    $this->image->set_compression_quality($quality);

    if ($strip_metadata)
    {
      // We save a few kilobytes. For example, a thumbnail with metadata weights 25KB, without metadata 7KB.
      $this->image->strip();
    }

    if (isset($resize_dimensions['crop']))
    {
      $this->image->crop($resize_dimensions['crop']['width'], $resize_dimensions['crop']['height'], $resize_dimensions['crop']['x'], $resize_dimensions['crop']['y']);
    }

    $this->image->resize($resize_dimensions['width'], $resize_dimensions['height']);

    if (!empty($rotation))
    {
      $this->image->rotate($rotation);
    }

    $this->image->write($destination_filepath);

    // everything should be OK if we are here!
    return $this->get_resize_result($destination_filepath, $resize_dimensions['width'], $resize_dimensions['height'], $starttime);
  }

  static function get_resize_dimensions($width, $height, $max_width, $max_height, $rotation=null, $crop=false, $follow_orientation=true)
  {
    $rotate_for_dimensions = false;
    if (isset($rotation) and in_array(abs($rotation), array(90, 270)))
    {
      $rotate_for_dimensions = true;
    }

    if ($rotate_for_dimensions)
    {
      list($width, $height) = array($height, $width);
    }

    if ($crop)
    {
      $x = 0;
      $y = 0;

      if ($width < $height and $follow_orientation)
      {
        list($max_width, $max_height) = array($max_height, $max_width);
      }

      $img_ratio = $width / $height;
      $dest_ratio = $max_width / $max_height;

      if($dest_ratio > $img_ratio)
      {
        $destHeight = round($width * $max_height / $max_width);
        $y = round(($height - $destHeight) / 2 );
        $height = $destHeight;
      }
      elseif ($dest_ratio < $img_ratio)
      {
        $destWidth = round($height * $max_width / $max_height);
        $x = round(($width - $destWidth) / 2 );
        $width = $destWidth;
      }
    }

    $ratio_width  = $width / $max_width;
    $ratio_height = $height / $max_height;
    $destination_width = $width;
    $destination_height = $height;

    // maximal size exceeded ?
    if ($ratio_width > 1 or $ratio_height > 1)
    {
      if ($ratio_width < $ratio_height)
      {
        $destination_width = round($width / $ratio_height);
        $destination_height = $max_height;
      }
      else
      {
        $destination_width = $max_width;
        $destination_height = round($height / $ratio_width);
      }
    }

    if ($rotate_for_dimensions)
    {
      list($destination_width, $destination_height) = array($destination_height, $destination_width);
    }

    $result = array(
      'width' => $destination_width,
      'height'=> $destination_height,
      );

    if ($crop and ($x or $y))
    {
      $result['crop'] = array(
        'width' => $width,
        'height' => $height,
        'x' => $x,
        'y' => $y,
        );
    }
    return $result;
  }

  static function webp_info($source_filepath)
  {
    // function based on https://stackoverflow.com/questions/61221874/detect-if-a-webp-image-is-transparent-in-php
    //
    // https://github.com/webmproject/libwebp/blob/master/src/dec/webp_dec.c
    // https://developers.google.com/speed/webp/docs/riff_container
    // https://developers.google.com/speed/webp/docs/webp_lossless_bitstream_specification
    // https://stackoverflow.com/questions/61221874/detect-if-a-webp-image-is-transparent-in-php

    $fp = fopen($source_filepath, 'rb');
    if (!$fp) {
        throw new Exception("webp_info(): fopen($f): Failed");
    }
    $buf = fread($fp, 25);
    fclose($fp);

    switch (true) {
      case!is_string($buf):
      case strlen($buf) < 25:
      case substr($buf, 0, 4) != 'RIFF':
      case substr($buf, 8, 4) != 'WEBP':
      case substr($buf, 12, 3) != 'VP8':
        throw new Exception("webp_info(): not a valid webp image");

      case $buf[15] == ' ':
        // Simple File Format (Lossy)
        return array(
          'type'            => 'VP8',
          'has-animation'   => false,
          'has-transparent' => false,
        );


      case $buf[15] == 'L':
        // Simple File Format (Lossless)
        return array(
          'type'            => 'VP8L',
          'has-animation'   => false,
          'has-transparent' => (bool) (!!(ord($buf[24]) & 0x00000010)),
        );

      case $buf[15] == 'X':
        // Extended File Format
        return array(
          'type'            => 'VP8X',
          'has-animation'   => (bool) (!!(ord($buf[20]) & 0x00000002)),
          'has-transparent' => (bool) (!!(ord($buf[20]) & 0x00000010)),
        );

      default:
        throw new Exception("webp_info(): could not detect webp type");
    }
  }

  static function get_rotation_angle($source_filepath)
  {
    list($width, $height, $type) = getimagesize($source_filepath);
    if (IMAGETYPE_JPEG != $type)
    {
      return null;
    }

    if (!function_exists('exif_read_data'))
    {
      return null;
    }

    $rotation = 0;

    getimagesize($source_filepath, $info);

    // Check if the APP1 segment exists in the info array
    if (! isset($info['APP1']) || ! str_starts_with((string) $info['APP1'], 'Exif')) {
        return 0;
    }

    $exif = exif_read_data($source_filepath);

    if (isset($exif['Orientation']) and preg_match('/^\s*(\d)/', (string) $exif['Orientation'], $matches))
    {
      $orientation = $matches[1];
      if (in_array($orientation, array(3, 4)))
      {
        $rotation = 180;
      }
      elseif (in_array($orientation, array(5, 6)))
      {
        $rotation = 270;
      }
      elseif (in_array($orientation, array(7, 8)))
      {
        $rotation = 90;
      }
    }

    return $rotation;
  }

  static function get_rotation_code_from_angle($rotation_angle)
  {
    switch($rotation_angle)
    {
      case 0:   return 0;
      case 90:  return 1;
      case 180: return 2;
      case 270: return 3;
    }
  }

  static function get_rotation_angle_from_code($rotation_code)
  {
    switch($rotation_code%4)
    {
      case 0: return 0;
      case 1: return 90;
      case 2: return 180;
      case 3: return 270;
    }
  }

  /** Returns a normalized convolution kernel for sharpening*/
  static function get_sharpen_matrix($amount)
  {
    // The amount should be in the range of 48-10
    $amount = round(abs(-48 + ($amount * 0.38)), 2);

    $matrix = array(
      array(-1,   -1,    -1),
      array(-1, $amount, -1),
      array(-1,   -1,    -1),
      );

    $norm = array_sum(array_map('array_sum', $matrix));

    for ($i=0; $i<3; $i++)
    {
      for ($j=0; $j<3; $j++)
      {
        $matrix[$i][$j] /= $norm;
      }
    }

    return $matrix;
  }

  private function get_resize_result($destination_filepath, $width, $height, $time=null)
  {
    return array(
      'source'      => $this->source_filepath,
      'destination' => $destination_filepath,
      'width'       => $width,
      'height'      => $height,
      'size'        => floor(filesize($destination_filepath) / 1024).' KB',
      'time'        => $time ? number_format((get_moment() - $time) * 1000, 2, '.', ' ').' ms' : null,
      'library'     => $this->library,
    );
  }

  static function is_vips()
  {
    return class_exists('image_vips');
  }

  static function get_library($library=null, $extension=null)
  {
    global $conf;

    if (is_null($library))
    {
      $library = $conf['graphics_library'];
    }

    // Choose the image library
    switch (strtolower($library))
    {
      case 'auto':
      case 'vips':
        if (self::is_vips())
        {
          return 'vips';
        }
      default:
        if ($library != 'auto')
        {
          // The requested library is not available. Try another library
          return self::get_library('auto', $extension);
        }
    }
    return false;
  }

  function destroy()
  {
    if (method_exists($this->image, 'destroy'))
    {
      return $this->image->destroy();
    }
    return true;
  }
}

// +-----------------------------------------------------------------------+
// |                       Class for libvips library                       |
// +-----------------------------------------------------------------------+

class image_vips implements imageInterface
{
    public Jcupitt\Vips\Image $image;

    public $quality = 75;

    public $source_filepath;

    public function __construct(
        $source_filepath
    ) {
        // putenv('VIPS_WARNING=0');
        $this->image = Jcupitt\Vips\Image::newFromFile(realpath($source_filepath), [
            'access' => 'sequential',
        ]);
        $this->source_filepath = realpath($source_filepath);
    }

    public function add_command($command, $params = null)
    {

    }

    #[\Override]
    public function get_width()
    {
        return $this->image->width;
    }

    #[\Override]
    public function get_height()
    {
        return $this->image->height;
    }

    #[\Override]
    public function crop($width, $height, $x, $y)
    {
        $this->image = $this->image->crop($x, $y, $width, $height);
        return true;
    }

    #[\Override]
    public function strip()
    {
        return true;
    }

    #[\Override]
    public function rotate($rotation)
    {
        $this->image = $this->image->rotate($rotation);
        return true;
    }

    #[\Override]
    public function set_compression_quality($quality)
    {
        $this->quality = $quality;
        return true;
    }

    #[\Override]
    public function resize($width, $height)
    {
        $this->image = Jcupitt\Vips\Image::thumbnail($this->source_filepath, $width, [
            'height' => $height,
        ]);
        return true;
    }

    #[\Override]
    public function sharpen($amount)
    {
        return true;
    }

    #[\Override]
    public function compose($overlay, $x, $y, $opacity)
    {
        return true;
    }

    #[\Override]
    public function write($destination_filepath)
    {
        $dest = pathinfo((string) $destination_filepath);
        $this->image->writeToFile(realpath($dest['dirname']) . '/' . $dest['basename']);
        return true;
    }
}

?>
