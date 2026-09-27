<?php
// Image uploads. The type is decided from the file content, never from the client's file
// name, so "photo.php" can't be uploaded and executed.

class Uploads
{
    const MAX_BYTES = 5 * 1024 * 1024;
    const TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    const GYM_LOGOS = 'gym_logos/';
    const MEMBER_PHOTOS = 'members/';

    /**
     * Save an uploaded image. Returns the relative path stored in the DB ("uploads/…"),
     * or null when $file is null.
     */
    public static function saveImage($file, $folder, $prefix)
    {
        if ($file === null) {
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new ValidationException("File upload failed. Please try again.");
        }
        if ($file['size'] > self::MAX_BYTES) {
            throw new ValidationException("File size too large. Maximum 5MB allowed.");
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset(self::TYPES[$mime]) || @getimagesize($file['tmp_name']) === false) {
            throw new ValidationException("Only JPG, PNG, WEBP or GIF images are allowed.");
        }

        $dir = PUBLIC_ROOT . '/uploads/' . $folder;
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new RuntimeException("Upload dir could not be created: $dir");
        }

        $name = $prefix . bin2hex(random_bytes(8)) . '.' . self::TYPES[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . $name)) {
            throw new RuntimeException("move_uploaded_file failed for $dir$name");
        }
        @chmod($dir . $name, 0644);

        return 'uploads/' . $folder . $name;
    }

    /**
     * Delete a stored upload. Only files inside public/uploads are ever touched.
     */
    public static function delete($relativePath)
    {
        if (empty($relativePath) || strpos($relativePath, 'uploads/') !== 0) {
            return;
        }
        $base = realpath(PUBLIC_ROOT . '/uploads');
        $path = realpath(PUBLIC_ROOT . '/' . $relativePath);
        if ($base && $path && strpos($path, $base . DIRECTORY_SEPARATOR) === 0 && is_file($path)) {
            @unlink($path);
        }
    }
}
