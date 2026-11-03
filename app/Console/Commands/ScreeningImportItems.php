<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\Screening\ScreeningItemImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

#[Signature('screening:import {file : Caminho do JSON com os itens} {--organization= : id da organização (por omissão, a primeira)} {--demo : marcar os itens como fictícios}')]
#[Description('Importa o banco de itens do rastreio (substitui os itens dos blocos etários presentes no ficheiro).')]
class ScreeningImportItems extends Command
{
    public function handle(ScreeningItemImporter $importer): int
    {
        $file = $this->argument('file');

        if (! is_file($file)) {
            $this->error("Ficheiro não encontrado: {$file}");

            return self::FAILURE;
        }

        $data = json_decode(file_get_contents($file), true);
        $org = $this->option('organization') ? Organization::findOrFail($this->option('organization')) : Organization::query()->firstOrFail();

        try {
            $count = $importer->import($org->id, $data['items'] ?? [], (bool) $this->option('demo'));
        } catch (ValidationException $e) {
            foreach ($e->errors()['items'] as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $this->info("{$count} itens importados para «{$org->name}».");

        return self::SUCCESS;
    }
}
