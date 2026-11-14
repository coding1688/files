<?php

return [
    'storage_not_ready' => 'Complete the bucket, endpoint and credentials before enabling this storage.',
    'immutable_disk' => 'The storage identifier and provider cannot be changed.',
    'reserved_disk' => 'This identifier is reserved for a built-in storage template.',
    'storage_in_use' => 'This storage is in use. Create a new storage account and synchronize files instead.',
    'sync_busy' => 'A synchronization is active, or the task cannot be retried.',
    'default_storage' => 'Select another default storage before disabling this one.',
    'directory_required' => 'Enter a directory relative to storage/app.',
    'overlapping_directory' => 'Storage directories must not overlap.',
    'duplicate_bucket' => 'This bucket and endpoint are already assigned to another storage account.',
    'public_url_required' => 'Public storage requires a public access URL.',
    'legacy_url_references' => 'Some consumers still store full image URLs (for example article bodies or logos). Convert those references to file IDs before changing this domain.',
    'visibility_mismatch' => 'Source files and target storage must have the same visibility; private files cannot become public.',
];
