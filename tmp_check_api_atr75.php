<?php
function fetchItems(string $empresa): array {
    $url = 'https://apisj.azurewebsites.net/fe/ApiSJ/api/ConsultaCentroCostos?strToken=87eb2d56-25f3-4d46-9cb0-73c07a550bd2&intIdEmpresa=' . urlencode($empresa);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSL_VERIFYPEER => 0,
    ]);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        echo "empresa={$empresa} curl_error={$err}\n";
        return [];
    }
    echo "empresa={$empresa} http={$code}\n";
    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        echo "empresa={$empresa} respuesta_no_json\n";
        return [];
    }

    $items = [];
    if (array_is_list($decoded)) {
        $items = $decoded;
    } else {
        foreach (['result','Result','RESULT','Det','DET'] as $k) {
            if (isset($decoded[$k]) && is_array($decoded[$k])) {
                if (array_is_list($decoded[$k])) { $items = $decoded[$k]; break; }
                foreach (['Det','DET'] as $sub) {
                    if (isset($decoded[$k][$sub]) && is_array($decoded[$k][$sub])) { $items = $decoded[$k][$sub]; break 2; }
                }
            }
        }
    }

    echo "empresa={$empresa} items=" . count($items) . "\n";
    $with75 = 0;
    $with68 = 0;
    foreach ($items as $it) {
        if (!is_array($it)) continue;
        if (array_key_exists('Atr75', $it) && $it['Atr75'] !== null && $it['Atr75'] !== '') $with75++;
        if (array_key_exists('Atr68', $it) && $it['Atr68'] !== null && $it['Atr68'] !== '') $with68++;
    }
    echo "empresa={$empresa} con_Atr75={$with75} con_Atr68={$with68}\n";

    for ($i=0; $i < min(3, count($items)); $i++) {
        if (!is_array($items[$i])) continue;
        $keys = array_keys($items[$i]);
        $atrKeys = array_values(array_filter($keys, fn($k) => str_starts_with($k, 'Atr')));
        echo "empresa={$empresa} sample{$i}_atr_keys=" . implode(',', array_slice($atrKeys,0,20)) . "\n";
        if (array_key_exists('Atr75', $items[$i])) {
            echo "empresa={$empresa} sample{$i}_Atr75=" . var_export($items[$i]['Atr75'], true) . "\n";
        }
    }

    return $items;
}

fetchItems('168');
fetchItems('169');
