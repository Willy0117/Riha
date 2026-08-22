<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;

class FileService
{
    protected $disk;

    public function __construct()
    {
        $this->disk = config('filesystems.default');
    }

    /**
     * ファイル保存 + サムネイル
     */
    public function storeUploadedFile(UploadedFile $file, string $dir): array
    {
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();

        $path = $file->storeAs($dir, $filename, $this->disk);

        // サムネイルは、アップロード直後のローカル一時ファイル（$file->getRealPath()）から生成する。
        // S3ディスクには「ローカルパス」という概念が無いため、保存後のパスから再取得しようとすると失敗する。
        $thumbnail = $this->createThumbnailFromLocalFile($file->getRealPath(), $path, $dir);

        return [$path, $thumbnail];
    }

    /**
     * base64画像保存
     */
    public function storeBase64Image(string $base64, string $dir): array
    {
        if (str_contains($base64, ',')) {
            $base64 = explode(',', $base64)[1];
        }

        $data = base64_decode($base64);

        $filename = Str::uuid().'.png';

        $path = $dir.'/'.$filename;

        Storage::disk($this->disk)->put($path, $data);

        // サムネイル生成のため、いったんローカルの一時ファイルへ書き出す
        $tmpFile = tempnam(sys_get_temp_dir(), 'b64img');
        file_put_contents($tmpFile, $data);

        $thumbnail = $this->createThumbnailFromLocalFile($tmpFile, $path, $dir);

        @unlink($tmpFile);

        return [$path, $thumbnail];
    }

    /**
     * サムネイル生成
     *
     * $localSourcePath : 変換元ファイルの「ローカル」パス（アップロード直後の一時ファイル、
     *                    またはbase64をいったん書き出した一時ファイル）
     * $path            : ディスク（S3含む）上での本体ファイルの保存パス（拡張子判定に使う）
     * $dir             : サムネイルの保存先ディレクトリ（例: pdf_uploads）
     */
    public function createThumbnailFromLocalFile(string $localSourcePath, string $path, string $dir): ?string
    {
        try {
            $thumbDir = $dir.'/thumbnails';
            $thumbName = pathinfo($path, PATHINFO_FILENAME).'_thumb.png';
            $thumbPath = $thumbDir.'/'.$thumbName;

            $imagick = new Imagick();

            // 拡張子は「保存先パス」の方から判定する（一時ファイル名には拡張子が付かないため）
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            if ($ext === 'pdf') {
                $imagick->setResolution(150, 150);
                $imagick->readImage($localSourcePath.'[0]');
            } else {
                $imagick->readImage($localSourcePath);
            }

            $imagick->setImageFormat('png');
            $imagick->thumbnailImage(150, 150, true);

            // 生成したサムネイルも、いったんローカルの一時ファイルへ書き出してからディスク（S3含む）へアップロードする
            $thumbTmpFile = tempnam(sys_get_temp_dir(), 'thumb').'.png';
            $imagick->writeImage($thumbTmpFile);
            $imagick->clear();
            $imagick->destroy();

            Storage::disk($this->disk)->put($thumbPath, file_get_contents($thumbTmpFile));

            @unlink($thumbTmpFile);

            return $thumbPath;
        } catch (\Exception $e) {
            \Log::error('Thumbnail error: '.$e->getMessage());
            return null;
        }
    }
}