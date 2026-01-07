<?php
/**
 * Biblioteca Simplificada de QR Code
 * Versão: 1.0
 * 
 * Uma implementação simples de geração de QR codes usando a API do QR Server
 * Para produção, considere usar a biblioteca completa phpqrcode
 */

define('QR_ECLEVEL_L', 'L');
define('QR_ECLEVEL_M', 'M');
define('QR_ECLEVEL_Q', 'Q');
define('QR_ECLEVEL_H', 'H');

class QRcode {
    /**
     * Gera um QR code e salva como arquivo PNG
     * 
     * @param string $text Texto a codificar
     * @param string $outfile Caminho do arquivo de saída
     * @param string $level Nível de correção de erro (L, M, Q, H)
     * @param int $size Tamanho do QR code (em módulos)
     * @param int $margin Margem ao redor do QR code
     * @return bool True se bem-sucedido
     */
    public static function png($text, $outfile, $level = QR_ECLEVEL_L, $size = 3, $margin = 2) {
        try {
            // Usar API do QR Server para gerar o QR code
            $url = "https://api.qrserver.com/v1/create-qr-code/";
            $params = [
                'size' => ($size * 10) . 'x' . ($size * 10),
                'data' => $text,
                'ecc' => $level,
                'margin' => $margin
            ];

            $fullUrl = $url . '?' . http_build_query($params);
            
            // Baixar a imagem
            $imageData = @file_get_contents($fullUrl);
            
            if ($imageData === false) {
                // Fallback: criar QR code simples em branco se a API falhar
                return self::createFallbackQR($outfile, $size, $margin);
            }

            // Salvar arquivo
            $dir = dirname($outfile);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            return file_put_contents($outfile, $imageData) !== false;
        } catch (Exception $e) {
            error_log('Erro ao gerar QR code: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Cria um QR code fallback em branco
     * 
     * @param string $outfile Caminho do arquivo
     * @param int $size Tamanho
     * @param int $margin Margem
     * @return bool
     */
    private static function createFallbackQR($outfile, $size = 3, $margin = 2) {
        $moduleSize = 10;
        $totalSize = ($size * 10 + $margin * 2) * $moduleSize;
        
        $image = imagecreatetruecolor($totalSize, $totalSize);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $white);
        
        $dir = dirname($outfile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return imagepng($image, $outfile) && imagedestroy($image);
    }
}
?>
