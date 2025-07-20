<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use App\Models\PagebuilderProject;
use App\Models\User;

class WebPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'slug', 'meta_title', 'meta_description', 'meta_keywords',
        'canonical_url', 'robots_meta', 'og_title', 'og_description', 'og_image',
        'custom_css', 'custom_js', 'custom_meta', 'icon', 'header_image',
        'is_fixed', 'is_active', 'published_from', 'published_until', 'last_editor', 'language',
        'pagebuilder_project', 'settings'
    ];

    protected $casts = [
        'is_fixed' => 'boolean',
        'is_active' => 'boolean',
        'custom_meta' => 'array',
        'settings' => 'array',
        'published_from' => 'datetime',
        'published_until' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
    
        // Vor dem Speichern Slug und letzten Bearbeiter setzen
        static::saving(function ($page) {
            if (!$page->slug) {
                $page->slug = Str::slug($page->title);
            }
            if (auth()->check()) {
                $page->last_editor = auth()->id();
            }
        });
    
        static::created(function ($page) {
            $randomNumber = rand(1000, 9999);
            $projectName = "{$page->title} Content";
            $projectData = '{"assets":[],"styles":[],"pages":[{"frames":[{"component":{"type":"wrapper","attributes":{"id":"itix"},"components":[{"tagName":"section","classes":["text-gray-600","body-font","relative"],"attributes":{"id":"iyduu"},"components":[{"classes":["container","px-5","py-24","mx-auto"],"attributes":{"id":"i91ng"},"components":[{"classes":["flex","flex-col","text-center","w-full","mb-12"],"attributes":{"id":"in4uu"},"components":[{"type":"heading","classes":["sm:text-3xl","text-2xl","font-medium","title-font","mb-4","text-gray-900"],"attributes":{"id":"igmy6"},"components":[{"type":"textnode","content":"Neues Pagebuilder Project"}]},{"tagName":"p","type":"text","classes":["lg:w-2/3","mx-auto","leading-relaxed","text-base"],"attributes":{"id":"i0w6e"},"components":[{"type":"textnode","content":"Hier kannst du kreativ werden und deine Träume verwirklichen!"}]}]}]}]}],"doctype":"<!DOCTYPE html>","head":{"type":"head","components":[{"tagName":"meta","void":true,"attributes":{"charset":"utf-8"}},{"tagName":"meta","void":true,"attributes":{"name":"viewport","content":"width=device-width,initial-scale=1"}},{"tagName":"meta","void":true,"attributes":{"name":"robots","content":"index,follow"}},{"tagName":"meta","void":true,"attributes":{"name":"generator","content":"LMZ Studio Project"}},{"tagName":"link","type":"link","attributes":{"href":"https://admin850.regulierungs-check.de/adminresources/css/tailwind.min.css","rel":"stylesheet"}}]},"docEl":{"tagName":"html"}},"id":"8uKM3pEMmO8ZbWvE"}],"type":"main","id":"BGeRYNcKhJpNIMjv"}],"symbols":[],"dataSources":[],"custom":{"projectType":"web","id":""}}';

            $maxOrderId = PagebuilderProject::max('order_id') ?? 0;
            $maxOrderIdIterated = $maxOrderId + 1;
            $project = PagebuilderProject::create([
                'name' => $projectName,
                'data' => $projectData,
                'status' => 3, 
                'page' => [$page->slug],
                'position' => ['page'],
                'order_id' => $maxOrderIdIterated,
                'type' => 'page',
                'is_fixed' => true,
            ]);
            $page->pagebuilder_project = $project->id;
            $page->saveQuietly();
        });
    }
    

    // Prüft, ob die Seite aktuell veröffentlicht ist
    public function isPublished()
    {
        $now = Carbon::now();
        return $this->is_active && (!$this->published_from || $this->published_from <= $now)
            && (!$this->published_until || $this->published_until >= $now);
    }

    // Beziehung zum letzten Bearbeiter (User)
    public function editor()
    {
        return $this->belongsTo(User::class, 'last_editor');
    }

    // Beziehung zum Pagebuilder-Projekt
    public function project()
    {
        return $this->belongsTo(PagebuilderProject::class, 'pagebuilder_project');
    }
}
