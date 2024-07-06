<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Response;

class CopyController extends Controller
{
    public function copy($path): Response
    {
        // Separa o caminho em partes e ajusta a primeira parte
        $parts = explode('/', $path);
        $parts[0] = $parts[0] . '-2';
        $adjustedPath = implode("/", $parts);
        $localPath = public_path($adjustedPath);

        // Verifica se o arquivo existe localmente
        if (File::exists($localPath)) {
            // Obtém a extensão do arquivo
            $extension = pathinfo($localPath, PATHINFO_EXTENSION);
            // Define os tipos MIME suportados
            $mimeTypes = [
                'json' => 'application/json',
                'js' => 'text/javascript',
                'html' => 'text/html'
            ];
            // Define o tipo MIME com base na extensão do arquivo
            $contentType = $mimeTypes[$extension] ?? 'text/plain';

            // Lê o conteúdo do arquivo e retorna a resposta com o tipo MIME apropriado
            $filecontents = File::get($localPath);
            return response($filecontents, 200, ['Content-Type' => $contentType]);
        } else {
            // Faz uma requisição HTTP para obter o conteúdo do arquivo
            $response = Http::get("https://m.pgsoft-games.com/{$path}");

            if ($response->successful()) {
                // Cria o diretório se não existir
                $directoryPath = dirname($localPath);
                if (!File::exists($directoryPath)) {
                    File::makeDirectory($directoryPath, 0755, true);
                }
                // Salva o conteúdo do arquivo localmente
                File::put($localPath, $response->body());

                // Obtém a extensão do arquivo
                $extension = pathinfo($localPath, PATHINFO_EXTENSION);
                // Define os tipos MIME suportados
                $mimeTypes = [
                    'json' => 'application/json',
                    'js' => 'text/javascript',
                    'html' => 'text/html'
                ];
                // Define o tipo MIME com base na extensão do arquivo
                $contentType = $mimeTypes[$extension] ?? 'text/plain';

                // Retorna a resposta com o conteúdo do arquivo e o tipo MIME apropriado
                return response($response->body(), 200, ['Content-Type' => $contentType]);
            } else {
                // Retorna uma resposta de erro se a requisição falhar
                return response("Fora do ar", 404);
            }
        }
    }
}
