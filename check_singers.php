<?php

require_once 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

// Database configuration
$capsule = new Capsule;
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'hci',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

try {
    // Check singers in users table
    echo "🎤 SINGERS IN USERS TABLE:\n";
    $singers = Capsule::table('users')
        ->where('role', 'Singer')
        ->select('id', 'name', 'email', 'phone', 'role')
        ->get();
    
    if ($singers->count() > 0) {
        foreach ($singers as $singer) {
            echo "ID: {$singer->id}, Name: {$singer->name}, Email: {$singer->email}, Phone: {$singer->phone}\n";
        }
    } else {
        echo "❌ No singers found in users table\n";
    }
    
    echo "\n🎵 SONGS IN SONGS TABLE:\n";
    $songs = Capsule::table('songs')
        ->select('id', 'title', 'artist', 'genre')
        ->get();
    
    if ($songs->count() > 0) {
        foreach ($songs as $song) {
            echo "ID: {$song->id}, Title: {$song->title}, Artist: {$song->artist}, Genre: {$song->genre}\n";
        }
    } else {
        echo "❌ No songs found in songs table\n";
    }
    
    echo "\n📋 MUSIC SUBMISSIONS:\n";
    $submissions = Capsule::table('music_submissions')
        ->select('id', 'music_arranger_id', 'song_id', 'proposed_singer_id', 'current_state')
        ->get();
    
    if ($submissions->count() > 0) {
        foreach ($submissions as $submission) {
            echo "ID: {$submission->id}, Arranger: {$submission->music_arranger_id}, Song: {$submission->song_id}, Singer: {$submission->proposed_singer_id}, State: {$submission->current_state}\n";
        }
    } else {
        echo "❌ No submissions found\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}












