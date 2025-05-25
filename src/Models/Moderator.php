<?php

namespace App\Models;

class Moderator extends \Aternos\Model\GenericModel
{
    protected static bool $registry = true;
    protected static ?int $cache = 60; // Cache for 60 seconds

    public static function getName(): string
    {
        return "moderators";
    }

    protected static array $drivers = [
        \Aternos\Model\Driver\Mysqli\Mysqli::ID
    ];

    // Public properties for table fields
    public $id;
    public string $discord_id;
    public string $discord_username;
    public ?string $discord_avatar_url; // Nullable
    public ?string $notes;              // Nullable
    public ?string $last_fetched_at;    // Nullable, will be a timestamp string
    // created_at and updated_at are handled by the database
    // public $created_at;
    // public $updated_at;
}
