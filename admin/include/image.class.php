<?php declare(strict_types=1);
// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

// +-----------------------------------------------------------------------+
// |                           Image Interface                             |
// +-----------------------------------------------------------------------+

// Define all needed methods for image class

/**
 *
 */
interface imageInterface
{
  /**
   * @return mixed
   */
  public function get_width(): mixed;

  /**
   * @return mixed
   */
  public function get_height(): mixed;

  /**
   * @param int $quality
   * @return bool
   */
  public function set_compression_quality(int $quality): bool;

  /**
   * @param $width
   * @param $height
   * @param $x
   * @param $y
   * @return mixed
   */
  public function crop($width, $height, $x, $y): mixed;

  /**
   * @return mixed
   */
  public function strip(): mixed;

  /**
   * @param $rotation
   * @return mixed
   */
  public function rotate($rotation): mixed;

  /**
   * @param int $width
   * @param int $height
   * @return bool
   */
  public function resize(int $width, int $height): bool;

  /**
   * @param $amount
   * @return mixed
   */
  public function sharpen($amount): mixed;

  /**
   * @param $overlay
   * @param $x
   * @param $y
   * @param $opacity
   * @return mixed
   */
  public function compose($overlay, $x, $y, $opacity): mixed;

  /**
   * @param $destination_filepath
   * @return mixed
   */
  public function write($destination_filepath): mixed;
}

// +-----------------------------------------------------------------------+
// |                          Main Image Class                             |
// +-----------------------------------------------------------------------+

/**
 *
 */
class pwg_image
{
  public mixed $image;
  public string|false $library = '';
  public string $source_filepath = '';
  public static string $ext_imagick_version = '';

  /**
   * @param $source_filepath
   * @param $library
   */
  public function __construct($source_filepath, $library=null)
  {
    $this->source_filepath = $source_filepath;

    trigger_notify('load_image_library', array(&$this) );

    // if (is_object($this->image)) {
    //   return; // A plugin may have load its own library
    // }

    $extension = strtolower(get_extension($source_filepath));

    if (!in_array($extension, array('jpg', 'jpeg', 'png', 'gif')))
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

  // Unknow methods will be redirected to image object

  /**
   * @param $method
   * @param $arguments
   * @return mixed
   */
  public function __call($method, $arguments)
  {
    return call_user_func_array(array($this->image, $method), $arguments);
  }

  // Piwigo resize function

  /**
   * @param $destination_filepath
   * @param $max_width
   * @param $max_height
   * @param $quality
   * @param bool $automatic_rotation
   * @param bool $strip_metadata
   * @param bool $crop
   * @param bool $follow_orientation
   * @return array
   */
  public function pwg_resize($destination_filepath, $max_width, $max_height, $quality, bool $automatic_rotation=true, bool $strip_metadata=false, bool $crop=false, bool $follow_orientation=true): array
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
      // we save a few kilobytes. For example a thumbnail with metadata weights 25KB, without metadata 7KB.
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

  /**
   * @param $width
   * @param $height
   * @param $max_width
   * @param $max_height
   * @param $rotation
   * @param bool $crop
   * @param bool $follow_orientation
   * @return array
   */
  public static function get_resize_dimensions($width, $height, $max_width, $max_height, $rotation = null, bool $crop = false, bool $follow_orientation = true): array
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

  /**
   * @param $source_filepath
   * @return int|null
   */
  public static function get_rotation_angle($source_filepath): ?int
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

    $exif = @exif_read_data($source_filepath);

    if (isset($exif['Orientation']) and preg_match('/^\s*(\d)/', (string)$exif['Orientation'], $matches))
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

  /**
   * @param int|null $rotation_angle
   * @return int
   * @throws Exception
   */
  public static function get_rotation_code_from_angle(int|null $rotation_angle): int
  {
    return match ($rotation_angle)
    {
      0, null => 0,
      90 => 1,
      180 => 2,
      270 => 3,
      default => throw new Exception('Unexpected rotation angle:' . $rotation_angle),
    };
  }

  /**
   * @param int|null $rotation_code
   * @return int
   * @throws Exception
   */
  public static function get_rotation_angle_from_code(int|null $rotation_code): int
  {
    return match ($rotation_code % 4)
    {
      0 => 0,
      1 => 90,
      2 => 180,
      3 => 270,
      default => throw new Exception('Unexpected rotation code:' . $rotation_code),
    };
  }

  /** Returns a normalized convolution kernel for sharpening*/
  public static function get_sharpen_matrix($amount): array
  {
    // Amount should be in the range of 48-10
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

  /**
   * @param $destination_filepath
   * @param $width
   * @param $height
   * @param $time
   * @return array
   */
  private function get_resize_result($destination_filepath, $width, $height, $time = null): array
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

  /**
   * @return bool
   */
  public static function is_imagick(): bool
  {
    return (extension_loaded('imagick') and class_exists('Imagick'));
  }

  /**
   * @return bool
   */
  public static function is_ext_imagick(): bool
  {
    global $conf;

    if (!function_exists('exec'))
    {
      return false;
    }
    if (empty($conf['ext_imagick_dir'])) {
      return false;
    }
    exec($conf['ext_imagick_dir'].'convert -version', $returnarray);
    if (is_array($returnarray) and !empty($returnarray[0]) and preg_match('/ImageMagick/i', $returnarray[0]))
    {
      if (preg_match('/Version: ImageMagick (\d+\.\d+\.\d+-?\d*)/', $returnarray[0], $match))
      {
        self::$ext_imagick_version = $match[1];
      }
      return true;
    }
    return false;
  }

  /**
   * @return bool
   */
  public static function is_gd(): bool
  {
    return function_exists('gd_info');
  }

  /**
   * @return bool
   */
  public static function is_vips(): bool
  {
    return class_exists('image_vips');
  }

  /**
   * @param $library
   * @param $extension
   * @return false|string
   */
  public static function get_library($library = null, $extension = null): false|string
  {
    global $conf;

    if (is_null($library))
    {
      $library = $conf['graphics_library'];
    }

    // Choose image library
    switch (strtolower($library))
    {
      case 'auto':
      case 'vips':
        if (self::is_vips())
        {
          return 'vips';
        }
      case 'imagick':
        if ($extension != 'gif' and self::is_imagick())
        {
          return 'imagick';
        }
      case 'ext_imagick':
        if ($extension != 'gif' and self::is_ext_imagick())
        {
          return 'ext_imagick';
        }
      case 'gd':
        if (self::is_gd())
        {
          return 'gd';
        }
      default:
        if ($library != 'auto')
        {
          // Requested library not available. Try another library
          return self::get_library('auto', $extension);
        }
    }
    return false;
  }

  /**
   * @return true
   */
  public function destroy(): true
  {
    if (method_exists($this->image, 'destroy'))
    {
      return $this->image->destroy();
    }
    return true;
  }
}

// +-----------------------------------------------------------------------+
// |                   Class for Imagick extension                         |
// +-----------------------------------------------------------------------+

/**
 *
 */
class image_imagick implements imageInterface
{
  public Imagick $image;

  /**
   * @param $source_filepath
   * @throws ImagickException
   */
  public function __construct($source_filepath)
  {
    // A bug cause that Imagick class can not be extended
    $this->image = new Imagick($source_filepath);
  }

  /**
   * @return int
   * @throws ImagickException
   */
  public function get_width(): int
  {
    return $this->image->getImageWidth();
  }

  /**
   * @return int
   * @throws ImagickException
   */
  public function get_height(): int
  {
    return $this->image->getImageHeight();
  }

  /**
   * @param int $quality
   * @return bool
   * @throws ImagickException
   */
  public function set_compression_quality(int $quality): bool
  {
    return $this->image->setImageCompressionQuality($quality);
  }

  /**
   * @param $width
   * @param $height
   * @param $x
   * @param $y
   * @return bool
   * @throws ImagickException
   */
  public function crop($width, $height, $x, $y): bool
  {
    return $this->image->cropImage($width, $height, $x, $y);
  }

  /**
   * @return bool
   * @throws ImagickException
   */
  public function strip(): bool
  {
    return $this->image->stripImage();
  }

  /**
   * @param $rotation
   * @return true
   * @throws ImagickException
   */
  public function rotate($rotation): true
  {
    $this->image->rotateImage(new ImagickPixel(), -$rotation);
    $this->image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
    return true;
  }

  /**
   * @param int $width
   * @param int $height
   * @return bool
   * @throws ImagickException
   */
  public function resize(int $width, int $height): bool
  {
    $this->image->setInterlaceScheme(Imagick::INTERLACE_LINE);

    // TODO need to explain this condition
    if ($this->get_width()%2 == 0
        && $this->get_height()%2 == 0
        && $this->get_width() > 3*$width)
    {
      $this->image->scaleImage($this->get_width()/2, $this->get_height()/2);
    }

    return $this->image->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 0.9);
  }

  /**
   * @param $amount
   * @return bool
   * @throws ImagickException
   */
  public function sharpen($amount): bool
  {
    $m = pwg_image::get_sharpen_matrix($amount);
    return  $this->image->convolveImage($m);
  }

  /**
   * @param $overlay
   * @param $x
   * @param $y
   * @param $opacity
   * @return bool
   * @throws ImagickException
   */
  public function compose($overlay, $x, $y, $opacity): bool
  {
    $ioverlay = $overlay->image->image;
    /*if ($ioverlay->getImageAlphaChannel() !== Imagick::ALPHACHANNEL_OPAQUE)
    {
      // Force the image to have an alpha channel
      $ioverlay->setImageAlphaChannel(Imagick::ALPHACHANNEL_OPAQUE);
    }*/

    global $dirty_trick_xrepeat;
    if ( !isset($dirty_trick_xrepeat) && $opacity < 100)
    {// NOTE: Using setImageOpacity will destroy current alpha channels!
      $ioverlay->evaluateImage(Imagick::EVALUATE_MULTIPLY, $opacity / 100, Imagick::CHANNEL_ALPHA);
      $dirty_trick_xrepeat = true;
    }

    return $this->image->compositeImage($ioverlay, Imagick::COMPOSITE_DISSOLVE, $x, $y);
  }

  /**
   * @param $destination_filepath
   * @return bool
   * @throws ImagickException
   */
  public function write($destination_filepath): bool
  {
    // use 4:2:2 chroma subsampling (reduce file size by 20-30% with "almost" no human perception)
    $this->image->setSamplingFactors( array(2,1) );
    return $this->image->writeImage($destination_filepath);
  }
}

// +-----------------------------------------------------------------------+
// |            Class for ImageMagick external installation                |
// +-----------------------------------------------------------------------+

/**
 *
 */
class image_ext_imagick implements imageInterface
{
  public mixed $imagickdir = '';
  public string $source_filepath = '';
  public int $width;
  public int $height;
  public array $commands = array();

  /**
   * @param $source_filepath
   */
  public function __construct($source_filepath)
  {
    global $conf;
    $this->source_filepath = $source_filepath;
    $this->imagickdir = $conf['ext_imagick_dir'];

    if (str_starts_with($_SERVER['SCRIPT_FILENAME'], '/kunden/'))  // 1and1
    {
      putenv('MAGICK_THREAD_LIMIT=1');
    }

    $command = $this->imagickdir.'identify -quiet -format "%wx%h" "'.realpath($source_filepath).'"';
    exec($command, $returnarray);
    if(!is_array($returnarray) or empty($returnarray[0]) or !preg_match('/^(\d+)x(\d+)$/', $returnarray[0], $match))
    {
      die("[External ImageMagick] Corrupt image\n" . var_export($returnarray, true));
    }

    $this->width = $match[1];
    $this->height = $match[2];
  }

  /**
   * @param $command
   * @param $params
   * @return void
   */
  public function add_command($command, $params = null): void
  {
    $this->commands[$command] = $params;
  }

  /**
   * @return string
   */
  public function get_width(): string
  {
    return $this->width;
  }

  /**
   * @return string
   */
  public function get_height(): string
  {
    return $this->height;
  }

  /**
   * @param $width
   * @param $height
   * @param $x
   * @param $y
   * @return true
   */
  public function crop($width, $height, $x, $y): true
  {
    $this->width = $width;
    $this->height = $height;

    $this->add_command('crop', $width.'x'.$height.'+'.$x.'+'.$y);
    return true;
  }

  /**
   * @return true
   */
  public function strip(): true
  {
    $this->add_command('strip');
    return true;
  }

  /**
   * @param $rotation
   * @return true
   */
  public function rotate($rotation): true
  {
    if (empty($rotation))
    {
      return true;
    }

    if ($rotation==90 || $rotation==270)
    {
      $tmp = $this->width;
      $this->width = $this->height;
      $this->height = $tmp;
    }
    $this->add_command('rotate', -$rotation);
    $this->add_command('orient', 'top-left');
    return true;
  }

  /**
   * @param int $quality
   * @return true
   */
  public function set_compression_quality(int $quality): true
  {
    $this->add_command('quality', $quality);
    return true;
  }

  /**
   * @param int $width
   * @param int $height
   * @return true
   */
  public function resize(int $width, int $height): true
  {
    $this->width = $width;
    $this->height = $height;

    $this->add_command('filter', 'Lanczos');
    $this->add_command('resize', $width.'x'.$height.'!');
    return true;
  }

  /**
   * @param $amount
   * @return true
   */
  public function sharpen($amount): true
  {
    $m = pwg_image::get_sharpen_matrix($amount);

    $param ='convolve "'.count($m).':';
    foreach ($m as $line)
    {
      $param .= ' ';
      $param .= implode(',', $line);
    }
    $param .= '"';
    $this->add_command('morphology', $param);
    return true;
  }

  /**
   * @param $overlay
   * @param $x
   * @param $y
   * @param $opacity
   * @return true
   */
  public function compose($overlay, $x, $y, $opacity): true
  {
    $param = 'compose dissolve -define compose:args='.$opacity;
    $param .= ' '.escapeshellarg(realpath($overlay->image->source_filepath));
    $param .= ' -gravity NorthWest -geometry +'.$x.'+'.$y;
    $param .= ' -composite';
    $this->add_command($param);
    return true;
  }

  /**
   * @param $destination_filepath
   * @return bool
   */
  public function write($destination_filepath): bool
  {
    global $logger;

    $this->add_command('interlace', 'line'); // progressive rendering
    // use 4:2:2 chroma subsampling (reduce file size by 20-30% with "almost" no human perception)
    //
    // option deactivated for Piwigo 2.4.1, it doesn't work fo old versions
    // of ImageMagick, see bug:2672. To reactivate once we have a better way
    // to detect IM version and when we know which version supports this
    // option
    //
    if (version_compare(pwg_image::$ext_imagick_version, '6.6') > 0)
    {
      $this->add_command('sampling-factor', '4:2:2' );
    }

    $exec = $this->imagickdir.'convert -quiet';
    $exec .= ' "'.realpath($this->source_filepath).'"';

    foreach ($this->commands as $command => $params)
    {
      $exec .= ' -'.$command;
      if (!empty($params))
      {
        $exec .= ' '.$params;
      }
    }

    $dest = pathinfo($destination_filepath);
    $exec .= ' "'.realpath($dest['dirname']).'/'.$dest['basename'].'" 2>&1';
    $logger->debug($exec);
    exec($exec, $returnarray);

    if (is_array($returnarray) && (count($returnarray)>0) )
    {
      $logger->error('', $returnarray);
      foreach ($returnarray as $line)
        trigger_error($line, E_USER_WARNING);
    }
    return is_array($returnarray);
  }
}

// +-----------------------------------------------------------------------+
// |                       Class for GD library                            |
// +-----------------------------------------------------------------------+

/**
 *
 */
class image_gd implements imageInterface
{
  public $image;
  public int $quality = 95;

  /**
   * @param $source_filepath
   */
  public function __construct($source_filepath)
  {
    $gd_info = gd_info();
    $extension = strtolower(get_extension($source_filepath));

    if (in_array($extension, array('jpg', 'jpeg')))
    {
      $this->image = imagecreatefromjpeg($source_filepath);
    }
    elseif ($extension == 'png')
    {
      $this->image = imagecreatefrompng($source_filepath);
    }
    elseif ($extension == 'gif' and $gd_info['GIF Read Support'] and $gd_info['GIF Create Support'])
    {
      $this->image = imagecreatefromgif($source_filepath);
    }
    else
    {
      die('[Image GD] unsupported file extension');
    }
  }

  /**
   * @return false|int
   */
  public function get_width(): false|int
  {
    return imagesx($this->image);
  }

  /**
   * @return false|int
   */
  public function get_height(): false|int
  {
    return imagesy($this->image);
  }

  /**
   * @param $width
   * @param $height
   * @param $x
   * @param $y
   * @return bool
   */
  public function crop($width, $height, $x, $y): bool
  {
    $dest = imagecreatetruecolor($width, $height);

    imagealphablending($dest, false);
    imagesavealpha($dest, true);
    if (function_exists('imageantialias'))
    {
      imageantialias($dest, true);
    }

    $result = imagecopymerge($dest, $this->image, 0, 0, $x, $y, $width, $height, 100);

    if ($result !== false)
    {
      imagedestroy($this->image);
      $this->image = $dest;
    }
    else
    {
      imagedestroy($dest);
    }
    return $result;
  }

  /**
   * @return true
   */
  public function strip(): true
  {
    return true;
  }

  /**
   * @param $rotation
   * @return true
   */
  public function rotate($rotation): true
  {
    $dest = imagerotate($this->image, $rotation, 0);
    imagedestroy($this->image);
    $this->image = $dest;
    return true;
  }

  /**
   * @param int $quality
   * @return true
   */
  public function set_compression_quality(int $quality): true
  {
    $this->quality = $quality;
    return true;
  }

  /**
   * @param int $width
   * @param int $height
   * @return bool
   */
  public function resize(int $width, int $height): bool
  {
    $dest = imagecreatetruecolor($width, $height);

    imagealphablending($dest, false);
    imagesavealpha($dest, true);
    if (function_exists('imageantialias'))
    {
      imageantialias($dest, true);
    }

    $result = imagecopyresampled($dest, $this->image, 0, 0, 0, 0, $width, $height, $this->get_width(), $this->get_height());

    if ($result !== false)
    {
      imagedestroy($this->image);
      $this->image = $dest;
    }
    else
    {
      imagedestroy($dest);
    }
    return $result;
  }

  /**
   * @param $amount
   * @return bool
   */
  public function sharpen($amount): bool
  {
    $m = pwg_image::get_sharpen_matrix($amount);
    return imageconvolution($this->image, $m, 1, 0);
  }

  /**
   * @param $overlay
   * @param $x
   * @param $y
   * @param $opacity
   * @return true
   */
  public function compose($overlay, $x, $y, $opacity): true
  {
    $ioverlay = $overlay->image->image;
    /* A replacement for php's imagecopymerge() function that supports the alpha channel
    See php bug #23815:  http://bugs.php.net/bug.php?id=23815 */

    $ow = imagesx($ioverlay);
    $oh = imagesy($ioverlay);

		// Create a new blank image the site of our source image
		$cut = imagecreatetruecolor($ow, $oh);

		// Copy the blank image into the destination image where the source goes
		imagecopy($cut, $this->image, 0, 0, $x, $y, $ow, $oh);

		// Place the source image in the destination image
		imagecopy($cut, $ioverlay, 0, 0, 0, 0, $ow, $oh);
		imagecopymerge($this->image, $cut, $x, $y, 0, 0, $ow, $oh, $opacity);
    imagedestroy($cut);
    return true;
  }

  /**
   * @param $destination_filepath
   * @return bool
   */
  public function write($destination_filepath): bool
  {
    $extension = strtolower(get_extension($destination_filepath));

    if ($extension == 'png')
    {
      imagepng($this->image, $destination_filepath);
    }
    elseif ($extension == 'gif')
    {
      imagegif($this->image, $destination_filepath);
    }
    else
    {
      imagejpeg($this->image, $destination_filepath, $this->quality);
    }

    return true;
  }

  /**
   * @return void
   */
  public function destroy(): void
  {
    imagedestroy($this->image);
  }
}

// +-----------------------------------------------------------------------+
// |                       Class for libvips library                       |
// +-----------------------------------------------------------------------+

/**
 *
 */
class image_vips implements imageInterface
{
  public Jcupitt\Vips\Image $image;
  public int $quality = 75;
  public string $source_filepath;

  /**
   * @param string $source_filepath
   * @throws \Jcupitt\Vips\Exception
   */
  public function __construct(string $source_filepath)
  {
    // putenv('VIPS_WARNING=0');
    $this->image = Jcupitt\Vips\Image::newFromFile(realpath($source_filepath), array('access' => 'sequential'));
    $this->source_filepath = realpath($source_filepath);
  }

  /**
   * @param $command
   * @param $params
   * @return void
   */
  public function add_command($command, $params = null): void
  {

  }

  /**
   * @return int
   */
  public function get_width(): int
  {
    return $this->image->width;
  }

  /**
   * @return int
   */
  public function get_height(): int
  {
    return $this->image->height;
  }

  /**
   * @param $width
   * @param $height
   * @param $x
   * @param $y
   * @return true
   */
  public function crop($width, $height, $x, $y): true
  {
    $this->image = $this->image->crop($x, $y, $width, $height);
    return true;
  }

  /**
   * @return true
   */
  public function strip(): true
  {
    return true;
  }

  /**
   * @param $rotation
   * @return true
   */
  public function rotate($rotation): true
  {
    $this->image = $this->image->rotate($rotation);
    return true;
  }

  /**
   * @param int $quality
   * @return true
   */
  public function set_compression_quality(int $quality): true
  {
    $this->quality = $quality;
    return true;
  }

  /**
   * @param int $width
   * @param int $height
   * @return true
   */
  public function resize(int $width, int $height): true
  {
    $this->image = Jcupitt\Vips\Image::thumbnail($this->source_filepath, $width, ['height' => $height]);
    return true;
  }

  /**
   * @param $amount
   * @return true
   */
  public function sharpen($amount): true
  {
    return true;
  }

  /**
   * @param $overlay
   * @param $x
   * @param $y
   * @param $opacity
   * @return true
   */
  public function compose($overlay, $x, $y, $opacity): true
  {
    return true;
  }

  /**
   * @param $destination_filepath
   * @return true
   * @throws \Jcupitt\Vips\Exception
   */
  public function write($destination_filepath): true
  {
    $dest = pathinfo($destination_filepath);
    $this->image->writeToFile(realpath($dest['dirname']) . '/' . $dest['basename']);
    return true;
  }
}

