<?php

/**
 * The single place LMS uploads land on disk.
 *
 * Mirrors the validation sequence of Booking::storeReportFile() — size, extension
 * allow-list, finfo mime that must match the extension, random destination name.
 * That method is private on Booking, so this is a deliberate ~20-line duplicate
 * rather than a refactor of the repo's most load-bearing file.
 *
 * Only the bare filename is ever returned or stored; callers build URLs with
 * url('uploads/lms/<subdir>/' . $filename).
 */
final class LmsFile
{
    /** Images only — used for lesson content blocks. */
    public const IMAGE_TYPES = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif' => 'image/gif',  'webp' => 'image/webp',
    ];

    /** Images + PDF — used for student mission attachments. */
    public const DOC_TYPES = self::IMAGE_TYPES + ['pdf' => 'application/pdf'];

    public const MAX_BYTES = 5 * 1024 * 1024;

    /** Whitelist of writable sub-directories under uploads/ — kills path traversal. */
    private const SUBDIRS = ['lms/missions', 'lms/blocks'];

    /**
     * Validates and moves one uploaded file.
     *
     * @param array $file    one entry of $_FILES
     * @param string $subdir one of self::SUBDIRS
     * @param array $allowed extension => expected mime map
     * @return array{ok:bool,error?:string,file?:string}
     */
    public static function store(array $file, string $subdir, string $prefix, int $ownerId, array $allowed, int $maxBytes = self::MAX_BYTES): array
    {
        if (!in_array($subdir, self::SUBDIRS, true)) {
            return ['ok' => false, 'error' => 'ปลายทางไฟล์ไม่ถูกต้อง'];
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => self::uploadErrorMessage((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE))];
        }
        if (($file['size'] ?? 0) > $maxBytes) {
            return ['ok' => false, 'error' => 'ไฟล์มีขนาดใหญ่เกิน ' . (int) round($maxBytes / 1048576) . ' MB'];
        }

        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!isset($allowed[$ext])) {
            $label = isset($allowed['pdf'])
                ? 'รองรับเฉพาะไฟล์รูปภาพ (JPG/PNG/GIF/WEBP) หรือ PDF'
                : 'รองรับเฉพาะไฟล์รูปภาพ (JPG/PNG/GIF/WEBP)';
            return ['ok' => false, 'error' => $label];
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if ($mime !== $allowed[$ext]) {
            return ['ok' => false, 'error' => 'ชนิดไฟล์ไม่ตรงกับนามสกุลไฟล์'];
        }

        $dir = self::dir($subdir);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $name = $prefix . '_' . $ownerId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            return ['ok' => false, 'error' => 'อัปโหลดไฟล์ไม่สำเร็จ'];
        }
        return ['ok' => true, 'file' => $name];
    }

    /** Deletes a stored file. Silently ignores a missing file or a bad subdir. */
    public static function remove(string $subdir, ?string $filename): void
    {
        if ($filename === null || $filename === '' || !in_array($subdir, self::SUBDIRS, true)) {
            return;
        }
        // Defence in depth: the DB only ever holds a bare filename, but never trust it as a path.
        $filename = basename($filename);
        $path = self::dir($subdir) . '/' . $filename;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Normalises a raw $_FILES multi-file array (array-of-arrays) into flat per-file
     * entries, skipping the empty slots the browser sends. Same shape-flattening
     * Booking::reportIssue() does for issue attachments.
     *
     * @return array<int,array{name:string,type:string,tmp_name:string,error:int,size:int}>
     */
    public static function normalizeMultiple(?array $raw): array
    {
        if (!$raw || !isset($raw['name']) || !is_array($raw['name'])) {
            return [];
        }
        $out = [];
        foreach ($raw['name'] as $i => $name) {
            if (($raw['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = [
                'name'     => (string) $name,
                'type'     => (string) ($raw['type'][$i] ?? ''),
                'tmp_name' => (string) ($raw['tmp_name'][$i] ?? ''),
                'error'    => (int) ($raw['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                'size'     => (int) ($raw['size'][$i] ?? 0),
            ];
        }
        return $out;
    }

    /** UPLOAD_ERR_* => Thai message, matching the map in admin/slots.php. */
    public static function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'ไฟล์มีขนาดใหญ่เกินกำหนด',
            UPLOAD_ERR_PARTIAL                        => 'อัปโหลดไฟล์ไม่ครบ กรุณาลองใหม่',
            UPLOAD_ERR_NO_FILE                        => 'ไม่พบไฟล์ที่อัปโหลด',
            UPLOAD_ERR_NO_TMP_DIR                     => 'เซิร์ฟเวอร์ไม่มีโฟลเดอร์ชั่วคราวสำหรับอัปโหลด',
            UPLOAD_ERR_CANT_WRITE                     => 'เซิร์ฟเวอร์เขียนไฟล์ไม่สำเร็จ',
            UPLOAD_ERR_EXTENSION                      => 'ส่วนขยายของ PHP ปฏิเสธการอัปโหลดไฟล์นี้',
            default                                   => 'อัปโหลดไฟล์ไม่สำเร็จ',
        };
    }

    private static function dir(string $subdir): string
    {
        return __DIR__ . '/../uploads/' . $subdir;
    }
}
