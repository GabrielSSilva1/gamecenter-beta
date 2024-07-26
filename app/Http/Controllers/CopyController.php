<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Response;

class CopyController extends Controller
{
    public function copy($path = null): Response
    {
        // Define o prefixo da pasta para arquivos sem slug
        $folderPrefix = 'cassino/';

        // Se $path for nulo, define como 'index.html' dentro da pasta 'cassino'
        $path = $path ? $folderPrefix . $path : $folderPrefix . 'index.html';

        // Remove parâmetros da URL para formar o caminho local
        $cleanPath = strtok($path, '?');

        // Adiciona "-2" ao nome da pasta para evitar conflitos
        $parts = explode('/', $cleanPath);
        $lastPart = array_pop($parts);
        $directoryPath = implode('/', $parts) . '/' . $lastPart . '-2';
        $localPath = public_path($directoryPath . '/' . $lastPart);

        // Cria a pasta correspondente ao slug com sufixo "-2"
        if (!File::exists(public_path($directoryPath))) {
            File::makeDirectory(public_path($directoryPath), 0755, true);
        }

        // Verifica se o arquivo já existe localmente
        if (File::exists($localPath) && !is_dir($localPath)) {
            return $this->respondWithFile($localPath);
        }

        // Ajusta o $path para a URL remota, removendo o prefixo 'cassino/' e codificando os parâmetros
        $remotePath = str_replace($folderPrefix, '', $cleanPath);
        $remoteUrl = "seusite" . urlencode($remotePath);

        // Configuração do proxy com autenticação
        $proxyOptions = [
            'proxy' => [
                'http'  => env('HTTP_PROXY'),
                'https' => env('HTTPS_PROXY'),
                'request_fulluri' => true,
                'auth'  => [
                    'username' => env('PROXY_USERNAME'),
                    'password' => env('PROXY_PASSWORD')
                ]
            ]
        ];

        // Faz a requisição para o servidor remoto usando o proxy configurado
        try {
            $response = Http::withOptions($proxyOptions)->get($remoteUrl);

            // Se a requisição for bem-sucedida
            if ($response->successful()) {
                // Salva o arquivo localmente
                $this->saveFileLocally($localPath, $response->body());

                // Retorna o arquivo
                return $this->respondWithFile($localPath);
            }

            // Registra o erro
            $this->logError("Failed to fetch file from remote server. URL: $remoteUrl, Status Code: {$response->status()}");
        } catch (\Exception $e) {
            // Registra o erro
            $this->logError("Exception while fetching file: {$e->getMessage()}");
        }

        // Se houver algum erro, retorna uma resposta de erro
        return response("Fora do ar", 404);
    }

    /**
     * Salva o conteúdo do arquivo localmente.
     *
     * @param string $path Caminho completo para salvar o arquivo.
     * @param string $content Conteúdo do arquivo.
     * @return void
     */
    private function saveFileLocally(string $path, string $content): void
    {
        // Cria o diretório se não existir
        $directoryPath = dirname($path);
        if (!File::exists($directoryPath)) {
            File::makeDirectory($directoryPath, 0755, true);
        }

        // Verifica se há um conflito entre o arquivo e o diretório
        if (is_dir($path)) {
            $path .= '/index.html';
        }

        // Salva o arquivo
        File::put($path, $content);
    }

    /**
     * Retorna uma resposta HTTP com o conteúdo do arquivo e o tipo MIME correto.
     *
     * @param string $path Caminho completo do arquivo.
     * @return Response
     */
    private function respondWithFile(string $path): Response
    {
        // Obtém a extensão do arquivo
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        // Define o tipo MIME com base na extensão do arquivo
        $contentType = $this->getMimeTypeByExtension($extension);

        // Lê o conteúdo do arquivo
        $fileContents = File::get($path);

        // Retorna a resposta com os cabeçalhos CORS
        return response($fileContents, 200, [
            'Content-Type' => $contentType,
            'Access-Control-Allow-Origin' => '*', // Permitir qualquer origem (ajuste para produção)
            'Access-Control-Allow-Methods' => 'POST, GET, OPTIONS, PUT, DELETE',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
        ]);
    }

    /**
     * Retorna o tipo MIME correspondente à extensão do arquivo.
     *
     * @param string|null $extension Extensão do arquivo.
     * @return string Tipo MIME.
     */
    private function getMimeTypeByExtension(?string $extension): string
    {
        // Define os tipos MIME suportados
        $mimeTypes = [
            'json' => 'application/json',
            'js' => 'text/javascript',
            'html' => 'text/html',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'css' => 'text/css',
            'svg' => 'image/svg+xml',
            'php' => 'application/x-httpd-php',
        ];

        // Retorna o tipo MIME correspondente à extensão, ou 'text/plain' caso não seja encontrado
        return $mimeTypes[$extension] ?? ($extension == 'php' ? 'text/html' : 'text/html');

    }

    /**
     * Salva um log de erro em um arquivo.
     *
     * @param string $message Mensagem de erro.
     * @return void
     */
    private function logError(string $message): void
    {
        // Define o caminho do arquivo de log
        $logPath = public_path('error.log');

        // Cria a mensagem de log com a data e hora atuais
        $logMessage = '[' . now() . '] ' . $message . PHP_EOL;

        // Adiciona a mensagem de log ao arquivo de log
        File::append($logPath, $logMessage);
    }
}
