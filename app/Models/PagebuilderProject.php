<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;


class PagebuilderProject extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 
        'data', 
        'html', 
        'cleaned_html', 
        'js', 
        'css', 
        'last_edited_by', 
        'page', 
        'position', 
        'lang', 
        'lock', 
        'published_from', 
        'published_until', 
        'order_id', 
        'status',
        'type'
    ];

    protected $casts = [
        'page' => 'array', // Position als JSON-Array
        'position' => 'array', // Position als JSON-Array
        'lock' => 'boolean',   // Lock als Boolean speichern
    ];



    protected static function boot()
    {
        parent::boot();

        static::updated(function ($project) {
            $project->updateHtmlContent($project);
            $project->setLastEditor();
        });
    }

    public function updateProjekt()
    {       
            $project = PagebuilderProject::find($this->id);
            $project->updateHtmlContent($project);
            $project->setLastEditor();
    }

    public function updateHtmlContent($project)
    {
        $project->updateHtmlFromData();
        $project->updateCssFromData();
    }   

    public function setLastEditor()
    {
        if (auth()->check()) {
            $this->updateQuietly(['last_edited_by' => auth()->id()]);
        }
    }    

    /**
     * Aktualisiert das `html`-Feld basierend auf `data`
     */

    public function updateHtmlFromData()
    {
        $html = $this->html;
        if (!empty($html)) {
            [$cleanedJsHtml, $extractedJs] = $this->extractScripts($html);
            $cleanedbodyAndJsHtml = $this->extractBodyContent($cleanedJsHtml);
            $this->cleaned_html = $cleanedbodyAndJsHtml;
            $this->js = $this->sanitizeJs($extractedJs);
            $this->updateQuietly([
                'cleaned_html' => $this->cleaned_html,
                'js' => $this->js
            ]);
        }
    }

    /**
     * Extrahiert `<script>`-Tags aus HTML und speichert sie separat in `js`
     */
    private function extractScripts($html)
    {
        $scriptTags = [];
        
        // Regulärer Ausdruck für `<script>`-Tags mit Inhalt
        $cleanedHtml = preg_replace_callback('/<script\b[^>]*>(.*?)<\/script>/is', function ($matches) use (&$scriptTags) {
            $scriptTags[] = trim($matches[1]); // Speichert den Skript-Inhalt
            return ''; // Entfernt `<script>`-Tag aus HTML
        }, $html);

        // Setzt alle gesammelten Skripte in ein einziges JavaScript-Block
        $extractedJs = implode("\n", $scriptTags);

        return [$cleanedHtml, $extractedJs];
    }

    private function sanitizeJs($js)
    {
        // Ersetze Swiper-CDN durch den lokalen Pfad (prüft nur auf http(s) und endet auf swiper-bundle.min.js)
        $js = preg_replace('/https?:\/\/[^"\']*swiper-bundle\.min\.js/i', '/adminresources/js/swiper-bundle.min.js', $js);
        $js = preg_replace('/https?:\/\/[^"\']*swiper-bundle\.min\.css/i', '/adminresources/css/swiper-bundle.min.css', $js);
        // Entferne alle anderen externen Skripte
        return preg_replace('/https?:\/\/(cdn\.(jsdelivr|cdnjs|unpkg)\.com|[^\s"\']+)/i', '', $js);
    }

    /**
     * Extrahiert den Inhalt aus dem <body>-Tag und ersetzt ihn durch ein <div>
     */
    private function extractBodyContent($html)
    {
        if (!$html || !Str::contains($html, '<body')) {
            return $html;
        }
            if (preg_match('/<body([^>]*)>(.*?)<\/body>/si', $html, $matches)) {
                $bodyAttributes = trim($matches[1]); 
                $bodyContent = trim($matches[2]); 
                $cleanedHtml = '<div ' . $bodyAttributes . '>' . $bodyContent . '</div>';
        
                return $cleanedHtml;
            }
            return $html;
    }

    /**
     * Aktualisiert das `css`-Feld basierend auf `data`
     */
    public function updateCssFromData()
    {
        $dataArray = json_decode($this->data, true);
        if (isset($dataArray['styles']) && is_array($dataArray['styles'])) {
            $cssRules = [];
            foreach ($dataArray['styles'] as $style) {
                if (!isset($style['selectors']) || !isset($style['style'])) {
                    continue; // Falls Selektoren oder Stile fehlen, überspringen
                }
                $selectors = implode(', ', $style['selectors']);
                $rules = [];
                foreach ($style['style'] as $property => $value) {
                    $rules[] = "{$property}: {$value};";
                }
                if (!empty($rules)) {
                    $cssRules[] = "{$selectors} { " . implode(' ', $rules) . " }";
                }
            }
            $cleanedCss = implode("\n", $cssRules);
            $this->updateQuietly(['css' => $cleanedCss]);
        }
    }

    
    /**
     * Beziehung zum letzten Bearbeiter (User).
     */
    public function lastEditor()
    {
        return $this->belongsTo(User::class, 'last_edited_by');
    }

    /**
     * Überprüft, ob das Projekt veröffentlicht ist.
     */
    public function isPublished()
    {
        $now = Carbon::now();
        return $this->status === 1 && $this->published_from && Carbon::parse($this->published_from)->lte($now)
            && (!$this->published_until || Carbon::parse($this->published_until)->gte($now));
    }

    /**
     * Setzt das Projekt als veröffentlicht.
     */
    public function publish()
    {
        $this->update([
            'status' => 1,
            'published_from' => now(),
        ]);
    }

    /**
     * Setzt das Projekt auf Entwurf zurück.
     */
    public function unpublish()
    {
        $this->update([
            'status' => 0,
            'published_from' => null,
            'published_until' => null,
        ]);
    }

    /**
     * Gibt den HTML-Inhalt zurück.
     */
    public function getHtml()
    {
        return $this->html;
    }

    /**
     * Gibt den Css-Inhalt zurück.
     */
    public function getCss()
    {
        return $this->css;
    }
}
