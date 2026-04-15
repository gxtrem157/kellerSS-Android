<?php
// ===== CONFIGURAÇÕES =====
$url_apk = "https://github.com/gxtrem157/kellerSS-Android/raw/refs/heads/main/KELLERSSSCANNER.apk";
$nome_apk = "KELLERSSSCANNER.apk";
$dir_download = "/sdcard/Download"; // Padrão, mas sobrepõe com usuário
$timeout_baixa = 300; // 5 minutos
$timeout_adb = 60; // 1 minuto

// ===== 1. VALIDAR DISPOSITIVO ADB (PASSO ZERO) =====
function validar_adb($timeout) {
    $start = time();
    while (time() - $start < $timeout) {
        $devices = shell_exec("adb devices 2>&1");
        if (preg_match("/\\tdevice\\b/", $devices)) {
            return true;
        }
        sleep(1);
    }
    return false;
}

// ===== 2. BAIXAR APK COM CONTROLE DE ERROS =====
function baixar_apk($url, $file, $timeout) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout / 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    $data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code != 200 || !$data) {
        throw new Exception("Falha no download (HTTP $http_code)");
    }
    if (file_put_contents($file, $data) === false) {
        throw new Exception("Sem permissão para gravar em $file");
    }
    return filesize($file);
}

// ===== 3. INSTALAR COM BYPASS DE PERMISSÕES =====
function instalar_com_bypass($file, $timeout) {
    $cmd = "adb -s shell pm install -i \"com.android.vending\" -r -d --bypass \"$file\" 2>&1";
    exec($cmd, $output, $return_var);
    return ["saida" => implode("\n", $output), "codigo" => $return_var];
}

// ===== EXECUÇÃO =====
// Perguntar diretório (prioridade do usuário)
$dir_input = readline("Digite o diretório do APK (ENTER p/ padrão: $dir_download): ");
$dir_download = trim($dir_input) ?: $dir_download;
$file = rtrim($dir_download, "/") . "/$nome_apk";

// Passo 1: Validar ADB
echo "[$] Validando dispositivo ADB (timeout: $timeout_adb segundos)...\n";
if (!validar_adb($timeout_adb)) {
    die("❌ Nenhum dispositivo ADB conectado ou autorizado. Habilite 'Depuração USB' no Android.\n");
}
echo "✅ Dispositivo ADB conectado e autorizado.\n";

// Passo 2: Baixar APK
echo "[$] Baixando APK ($url_apk)...\n";
try {
    $tamanho = baixar_apk($url_apk, $file, $timeout_baixa);
    echo "✅ APK baixado com sucesso ($tamanho bytes).\n";
} catch (Exception $e) {
    die("❌ {$e->getMessage()}\n");
}

// Passo 3: Instalar com bypass
echo "[$] Instalando APK via ADB com bypass...\n";
$resultado = instalar_com_bypass($file, $timeout_adb);
echo "📋 Resultado da instalação:\n" . $resultado["saida"] . "\n";

if ($resultado["codigo"] === 0) {
    echo "✅ Instalação bem-sucedida! Apague o arquivo '$file' quando quiser.\n";
} else {
    echo "⚠️ Instalação falhou com código {$resultado["codigo"]}.\nVerifique se:\n1. O APK está assinado corretamente.\n2. O dispositivo tem root (para --bypass funcionar).\n3. O ADB tem permissão.\n";
}
