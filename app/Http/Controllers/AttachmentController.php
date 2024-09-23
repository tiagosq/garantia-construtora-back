<?php

namespace App\Http\Controllers;

use App\Trait\Attachment;
use App\Trait\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use Illuminate\Validation\UnauthorizedException;


class AttachmentController extends Controller
{
    use Log, Attachment;

    public function view($business, $building, $maintenance, $filename) {
      $this->initLog(request());
      $filePath = storage_path("app/public/{$business}/{$building}/{$maintenance}/{$filename}");
      if (File::exists($filePath)) {
          $this->saveLog();
          return response()->file($filePath);
      }
      $this->saveLog();
      return response()->json(['error' => 'File not found.', 'file' => $filePath], 404);
    }
}