<?php

declare(strict_types=1);

return [
    'upload_failed' => 'The file could not be uploaded. Try again.',
    'too_large' => 'The file is larger than :limit.',
    'type_not_allowed' => 'Files of type :type are not accepted. Attach a photo (JPEG, PNG, WebP, HEIC) or a PDF.',
    'attachment' => 'Attachment',
    'attachments' => 'Attachments',
    'uploaded_by' => 'Uploaded by',
    'uploaded_at' => 'Uploaded',
    'download' => 'Open',

    // Said in terms of what to do, not of what the scanner is. "Come back in a
    // moment" is actionable; "scan status PENDING" is not.
    'scan_pending' => 'This file is still being checked. Try again in a moment.',
    'infected' => 'This file was rejected because a security check found a problem with it.',
];
