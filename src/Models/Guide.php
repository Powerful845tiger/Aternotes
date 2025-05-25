<?php

namespace App\Models;

class Guide extends \Aternos\Model\GenericModel 
{
    protected static bool $registry = true;
    protected static ?int $cache = 60; // Cache for 60 seconds

    public static function getName(): string
    {
        return "guides";
    }

    protected static array $drivers = [
        \Aternos\Model\Driver\Mysqli\Mysqli::ID
    ];

    // Public properties for table fields
    public $id;
    public string $title;
    public string $slug;
    public string $content_markdown;
    public string $content_html;
    public int $author_id; 
    public string $status;
    public $created_at; // Will be handled by DB or set in code
    public $updated_at; // Will be handled by DB or set in code
    public $published_at; // Nullable, will be set when published
}
