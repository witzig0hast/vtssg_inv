<?php

namespace App\Services;

use App\Models\AssetModel;
use Smalot\PdfParser\Parser;

/** Liest einen Technical Rider (Text/CSV/PDF) und schlägt passende Modelle aus dem Bestand vor. */
class RiderService
{
    public function extractText(?string $pasted, ?\Illuminate\Http\UploadedFile $file): string
    {
        if ($file) {
            $ext = strtolower($file->getClientOriginalExtension());
            if ($ext === 'pdf') {
                return (new Parser)->parseFile($file->getRealPath())->getText();
            }

            return (string) file_get_contents($file->getRealPath());
        }

        return (string) $pasted;
    }

    /** @return list<array{raw:string,qty:int,name:string,candidates:list<array{id:int,label:string,score:float}>}> */
    public function parse(string $text): array
    {
        $models = AssetModel::with('manufacturer')->get()->map(fn ($m) => [
            'model' => $m,
            'tokens' => $this->tokens($m->full_name.' '.$m->model_number),
        ]);

        $rows = [];
        foreach (preg_split('/\R/u', mb_substr($text, 0, 200000)) as $line) {
            $line = trim(preg_replace('/\s+/u', ' ', $line));
            if (mb_strlen($line) < 3 || mb_strlen($line) > 200) {
                continue;
            }
            [$qty, $name] = $this->splitQuantity($line);
            $tokens = $this->tokens($name);
            if (! $tokens) {
                continue;
            }
            $cands = $models->map(function ($m) use ($tokens) {
                $common = count(array_intersect($tokens, $m['tokens']));
                if ($common === 0) {
                    return null;
                }
                // Anteil der Rider-Wörter, die im Modell vorkommen, und umgekehrt
                $score = ($common / count($tokens)) * 0.6 + ($common / max(1, count($m['tokens']))) * 0.4;

                return ['id' => $m['model']->id, 'label' => $m['model']->full_name, 'score' => round($score, 2)];
            })->filter(fn ($c) => $c && $c['score'] >= 0.4)->sortByDesc('score')->take(4)->values()->all();

            // Zeilen ohne Treffer behalten nur, wenn sie wie eine Position aussehen (Menge erkannt)
            if ($cands || $qty > 1) {
                $rows[] = ['raw' => $line, 'qty' => $qty, 'name' => $name, 'candidates' => $cands];
            }
        }

        return array_slice($rows, 0, 300);
    }

    /** @return array{0:int,1:string} */
    protected function splitQuantity(string $line): array
    {
        $line = preg_replace('/^[\-\*•·]+\s*/u', '', $line);          // Aufzählungszeichen "- "
        $line = preg_replace('/^\d{1,2}[\.\)]\s+(?=\D)/u', '', $line); // nummerierte Liste "1. "
        if (preg_match('/^(\d{1,4})\s*(?:x|×|X|Stk\.?|Stück|pcs)?\s+(.+)$/u', $line, $m)) {
            return [max(1, (int) $m[1]), trim($m[2])];
        }
        if (preg_match('/^(.+?)\s*(?:x|×)\s*(\d{1,4})$/u', $line, $m)) {
            return [max(1, (int) $m[2]), trim($m[1])];
        }
        if (preg_match('/^(.+?)[;,\t]\s*(\d{1,4})$/u', $line, $m)) {
            return [max(1, (int) $m[2]), trim($m[1])];
        }

        return [1, $line];
    }

    /** @return list<string> */
    protected function tokens(string $s): array
    {
        $s = mb_strtolower($s);
        $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s);
        $stop = ['stk', 'stück', 'x', 'mit', 'und', 'für', 'the', 'and', 'inkl', 'oder'];

        return array_values(array_unique(array_filter(explode(' ', $s), fn ($t) => mb_strlen($t) > 1 && ! in_array($t, $stop, true))));
    }
}
