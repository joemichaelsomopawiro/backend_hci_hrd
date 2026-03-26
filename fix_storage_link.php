<?php

/**
 * Script to fix Laravel storage symlink on shared hosting (Hostinger)
 * 
 * Instructions:
 * 1. Upload this file to your public_html folder.
 * 2. Access it via: yourdomain.com/fix_storage_link.php
 * 3. Delete this file after success.
 */

$publicPath = __DIR__;
$storageLink = $publicPath . '/storage';
$targetPath = $publicPath . '/backend_hci/storage/app/public';

echo "<h1>Laravel Storage Link Fixer</h1>";

// 1. Check if target exists
if (!file_exists($targetPath)) {
    echo "<p style='color:red'>Error: Target path not found at $targetPath</p>";
    echo "<p>Please ensure 'backend_hci' is in the same folder as this script.</p>";
    exit;
}

// 2. Check if storage link already exists
if (file_exists($storageLink) || is_link($storageLink)) {
    echo "<p>Found existing 'storage' entry. Attempting to remove it...</p>";
    
    // If it's a directory, we need to be careful. Is it a link or a real folder?
    if (is_link($storageLink)) {
        if (unlink($storageLink)) {
            echo "<p style='color:green'>Success: Removed old symlink.</p>";
        } else {
            echo "<p style='color:red'>Error: Could not remove old symlink.</p>";
            exit;
        }
    } else if (is_dir($storageLink)) {
        // If it's a real directory and NOT empty, don't delete it automatically!
        $files = array_diff(scandir($storageLink), array('.', '..'));
        if (count($files) > 0) {
            echo "<p style='color:orange'>Warning: 'storage' is a real directory and is NOT empty. Please move its contents to $targetPath manually, then delete the folder and run this script again.</p>";
            exit;
        } else {
            if (rmdir($storageLink)) {
                echo "<p style='color:green'>Success: Removed empty storage directory.</p>";
            } else {
                echo "<p style='color:red'>Error: Could not remove empty directory.</p>";
                exit;
            }
        }
    } else {
        // It's a file
        if (unlink($storageLink)) {
            echo "<p style='color:green'>Success: Removed old storage file.</p>";
        } else {
            echo "<p style='color:red'>Error: Could not remove old storage file.</p>";
            exit;
        }
    }
}

// 3. Create the symlink
if (symlink($targetPath, $storageLink)) {
    echo "<p style='color:green; font-weight:bold;'>Success! Symmetric link 'storage' -> '$targetPath' has been created.</p>";
    echo "<p>You should now see the arrow icon in your File Manager.</p>";
} else {
    echo "<p style='color:red'>Error: symlink() function failed. Your hosting might have disabled it.</p>";
    echo "<p>Try using a different method or contact support.</p>";
}

echo "<hr><p style='color:blue'><b>IMPORTANT: Delete this file (fix_storage_link.php) now for security!</b></p>";
