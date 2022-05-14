<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ScaledImage404Controller extends Controller
{
    const R = '/\/(\[width_\d+\])\//';

    /**
     * @param string $fname имя файла (если нужен путь к файлу) Ex.: '0.jpg' по умолч пуст.строка
     * @return string Ex.: '/home/andrew/project/shop/public/storage/product/'  */
    public static function fsPath($fname='') {
        return public_path("storage/product/{$fname}");
    }

    /**
     * @param string $uri Ex.: '/storage/product/2.jpg'
     * @return string Ex.: '/home/andrew/project/shop/public/storage/product/2.jpg' */
    public static function uriToPath($uri='') {
        return public_path($uri);
    }

    /** вычислить mime-тип файла-изображения ('Content-type: image/'...) на основе расширения файла-изображения
     * @param string Имя файла\URI и т.п. Ex.1: '1.png' Ex.2: '/storage/product/10.jpg' Ex.3: '/home/andrew/project/shop/public/storage/product/2.jpg'
     * @return string|boolean тип  Ex.: 'png' или false если файл не из '*.gif', '*.jpeg', '*.jpg', '*.png' */
    public static function imageFileTypeFromExt($filename) {
        $ext = strtolower(fileext($filename));
        if ($ext==".jpg") $ext = '.jpeg';
        if (in_array($ext, ['.gif', '.jpeg', '.png'])) return substr($ext, 1);
        else return false;
    }


    /** Определить параметры масшатирования, проверить источник и место занзачения (созать если не создана папка назначения)
     * @param string $uri Ex.: '/storage/product/[width_100]/2.jpg'
     * @return array|boolean false если не требуется resize или ошибка; массив с осн. данными ресайза Ex.: ['outsize'=>'[width_100]', 'type'=>'jpeg', 'src'=>"/home/andrew/project/shop/public/storage/product/2.jpg", 'dest'=>"/home/andrew/project/shop/public/storage/product/[width_100]/2.jpg"] */
    public static function outsizeArr($uri) {
        // картинка или нет?
        $typeImage = static::imageFileTypeFromExt($uri); // 'png' | false
        if (false === $typeImage) return false;

        //содержит ли команду ресайза?
        $needResize = preg_match(static::R, $uri, $m); // $m = [0=>"/[width_100]/", 1=>"[width_100]"]
        if (!$needResize) return false;
        $outsize = $m[1]; // '[width_300]'

        // убрать outsize из $url
        $URI = str_replace($m[0], '/', $uri);  // '/storage/product/2.jpg'

        $startsWith = Str::of($URI)->startsWith($storage_uri = '/storage'); // true
        if (!$startsWith) return false;

        $file = static::uriToPath($URI); // '/home/andrew/project/shop/public/storage/product/2.jpg'
        if (!file_exists($file)) return false;

        //имя файла
        $name = basename($file); //'2.jpg'

        //путь для файла (без имени)
        $pathToCreate = static::uriToPath(substr($uri, 0,-1*strlen($name))); // '/home/andrew/project/shop/public/storage/product/[width_100]/2.jpg'

        //создать новый путь для файла если его ещё нет. при неудаче - выйти
        if (!file_exists($pathToCreate) && !mkdir($pathToCreate, 0777, true)) return false;

        return ['outsize'=>$outsize, 'type'=>$typeImage, 'src'=>$file, 'dest'=>$pathToCreate.$name];
    }


    /** Созд. img на основе имени файла */
    public static function getImage($filename) {
        $img = false;
        //угадываем тип по расширению
        $type = static::imageFileTypeFromExt($filename);
        switch ($type) {
            case 'png':	$img = @imagecreatefrompng($filename); break;
            case 'gif':	$img = @imagecreatefromgif($filename); break;
            case 'jpeg':$img = @imagecreatefromjpeg($filename);break;
        }
        //не угадали - пробуем всё подряд
        if (false === $img){
            $img = @imagecreatefromgif($filename);
            if (false !== $img) return $img;
            $img = @imagecreatefrompng($filename);
            if (false !== $img) return $img;
            $img = @imagecreatefromjpeg($filename);
            if (false !== $img) return $img;
        }
        //Log::debug('[app/Http/Controllers/ScaledImage404Controller] getImage('.json_encode($filename).') // '.($img ? '<image>' : 'false'));

        //если ничего не удалось - вернуть false
        return $img;
    }



    /** Ресайз изображений с сохранением пропорций
     * @param resource $im  gd image
     * @param string $outsize Строка с командой ресайза Ex.: "[width_200]"
     * @param string $type Один из типов 'jpeg'|'png'|'gif' для указания в "Content-type: image/{$type}" либо false
     * @return boolean|resource False - если ошибка или gd image если удалось перемасштабировать */
    public static function imageResize($im, $outsize, $type) {
        $old_w	= imagesx($im);
        $old_h	= imagesy($im);
        $new_w = $old_w;
        $new_h = $old_h;

        if (preg_match('/\[.*_(.+)\]/', $outsize, $m)) { // "[width_200]" => $m = ["[width_200]", '200']
            $new_w = intval($m[1]);
            $new_h = round(($new_w/$old_w) * $old_h); // k*old_h - расчёт новой высоты на основе коэф. масштабирования
        }

        //создание нового изображения с новыми размерами (его и будем возвращать)
        $image = imagecreatetruecolor($new_w, $new_h);

        // подготовка альфа-канала (для форматов поддерживающих прозрачность)
        if (in_array($type, ['png', 'gif'])) {
            imagesavealpha($image, true);
            $trans_colour = imagecolorallocatealpha($image, 0, 0, 0, 127);
            imagefilledrectangle($image, 0, 0, imagesx($image), imagesy($image), $trans_colour);
            imagealphablending($image, false);
        }

        //собственно масштабирование (копирование с растягиванием\сжатием картинки) и убивание более ненужного исходника
        if (imagecopyresampled($image, $im, 0, 0, 0, 0, $new_w, $new_h, $old_w, $old_h)) {
            imagedestroy($im);
        } else {
            Log::warning('[app/Http/Controllers/ScaledImage404Controller]::imageResize Picture not copied');
            imagedestroy($image);
            $image = $im;
        }


//        Log::debug('[app/Http/Controllers/ScaledImage404Controller]::'.sprintf(
//            'imageResize(im: %s, outsize: %s, type: %s) // %s',
//            $im ? '<image>' : 'false',
//            json_encode($outsize),
//            json_encode($type),
//            $image ? '<image>' : 'false'
//        ));
        return $image;
    }

}


/** расширение имени файла (включая точку)
 * @param string $filename имя файла с полным путём или без него
 * @return string расширение имени, включая точку или пустую строку, если нет расширения
 * Ex.1: fileext('script.js');//'.js'
 * Ex.2: fileext('www/.htaccess');//'.htaccess'
 * Ex.2: fileext('www/README');//'' */
function fileext($filename)	{
    $ext='';
    $filename=basename($filename); // оставили только имя
    if (($pos=strrpos($filename, '.'))!==false) $ext=substr($filename, $pos);
    return $ext;
}
