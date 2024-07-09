<?php

namespace App\Trait;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\Uid\Ulid;

trait Attachment
{
    public function saveAttachment(string $base64, array $pathSplitted, string $fileName) : array
    {
        $path = Str::join(DIRECTORY_SEPARATOR, $pathSplitted);

        if(!File::isDirectory($path))
        {
            File::makeDirectory($path, 0755, true, true);
        }

        $result = $this->validateBase64($base64);

        if ($result['status'])
        {
            $newFileName = Ulid::generate().'_'.$fileName;
            $result['file']->storeAs($path, $newFileName);

            return [
                'filename' => $newFileName,
                'path' => storage_path($path),
                'mimetype' => $this->getMimetype(Str::join(DIRECTORY_SEPARATOR, array_merge([storage_path($path), $newFileName]))),
            ];
        }

        return false;
    }

    public function deleteAttachment(array $pathSplitted, string $fileName) : bool
    {
        $path = Str::join(DIRECTORY_SEPARATOR, array_merge($pathSplitted, [$fileName]));

        if(File::isFile(storage_path($path)))
        {
            unlink(storage_path($path));
            return true;
        }

        return false;
    }



    private function validateBase64(string $base64data, array $allowedMimeTypes = null) : array
    {
        // strip out data URI scheme information (see RFC 2397)
        if (str_contains($base64data, ';base64'))
        {
            list(, $base64data) = explode(';', $base64data);
            list(, $base64data) = explode(',', $base64data);
        }

        // strict mode filters for non-base64 alphabet characters
        if (base64_decode($base64data, true) === false)
        {
            return [
                'status' => false,
                'message' => 'Unable to decode base64'
            ];
        }

        // decoding and then re-encoding should not change the data
        if (base64_encode(base64_decode($base64data)) !== $base64data)
        {
            return [
                'status' => false,
                'message' => 'Fail on verify consistency of file'
            ];
        }

        $fileBinaryData = base64_decode($base64data);

        // temporarily store the decoded data on the filesystem to be able to use it later on
        $tmpFileName = tempnam(sys_get_temp_dir(), 'attach');
        file_put_contents($tmpFileName, $fileBinaryData);

        $tmpFileObject = new File($tmpFileName);

        // guard against invalid mime types
        $allowedMimeTypes = Arr::flatten($allowedMimeTypes);

        // if there are no allowed mime types, then any type should be ok
        if (empty($allowedMimeTypes))
        {
            return [
                'status' => true,
                'message' => 'File converted',
                'mimetype' => $this->getMimetype($tmpFileName),
                'file' => $tmpFileObject
            ];
        }

        // Check the mime types
        $validation = Validator::make(
            ['file' => $tmpFileObject],
            ['file' => 'mimes:' . implode(',', $allowedMimeTypes)]
        );

        if($validation->fails())
        {
            return false;
        }

        return $tmpFileObject;
    }

    private function getMimetype(string $path) : string
    {
        $mime = null;
        // Try fileinfo first
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $path);
                finfo_close($finfo);
            }
        }
        // Fallback to mime_content_type() if finfo didn't work
        if (is_null($mime) && function_exists('mime_content_type')) {
            $mime = mime_content_type($path);
        }

        return (!empty($mime) ? $mime : "application/octet-stream");
    }
}
