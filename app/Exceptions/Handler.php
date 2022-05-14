<?php

namespace App\Exceptions;

use App\Http\Controllers\ScaledImage404Controller;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        //Log::debug('[app/Exceptions/Handler] register');
        $this->renderable(function (NotFoundHttpException $e, $request) {
            //Log::debug('[app/Exceptions/Handler] NotFoundHttpException '.$request->path());
            if (
                is_array($a = ScaledImage404Controller::outsizeArr("/".$request->path())) &&
                ($imSrc = ScaledImage404Controller::getImage($a['src'])) &&
                ($im = ScaledImage404Controller::imageResize($imSrc, $a['outsize'], $a['type']))
            ) {
                //Log::debug("[app/Exceptions/Handler] NotFoundHttpException saving '{$a['type']}' as '{$a['dest']}'");
                $type = $a['type'];
                ob_start();
                switch($type) {
                    case 'gif':
                        imagegif($im);
                        imagegif($im, $a['dest']);
                        break;
                    case 'png':
                        imagepng($im);
                        imagepng($im, $a['dest']);
                        break;
                    default:
                        imagejpeg($im);
                        imagejpeg($im, $a['dest']);
                }
                $buffer = ob_get_contents();
                ob_end_clean();
                //Log::debug("[app/Exceptions/Handler] NotFoundHttpException saving comlete. Destroy and return buffer");
                imagedestroy($im);
                return response($buffer, 200)->header('Content-type', 'image/'.$type);
            }
        });

    }
}
