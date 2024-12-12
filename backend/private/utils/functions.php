<?php
function generateJWT($payload) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode($payload);
    
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    $signature = hash_hmac('sha256', 
        $base64UrlHeader . "." . $base64UrlPayload, 
        $_ENV['JWT_SECRET'], 
        true
    );
    
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

function verifyJWT($token) {
    try {
        $tokenParts = explode('.', $token);
        
        if (count($tokenParts) != 3) {
            return false;
        }
        
        $signature = $tokenParts[2];
        $expectedSignature = hash_hmac('sha256',
            $tokenParts[0] . "." . $tokenParts[1],
            $_ENV['JWT_SECRET'],
            true
        );
        
        $expectedSignature = str_replace(
            ['+', '/', '='], 
            ['-', '_', ''], 
            base64_encode($expectedSignature)
        );
        
        if ($signature !== $expectedSignature) {
            return false;
        }
        
        $payload = json_decode(
            base64_decode(
                str_replace(['-', '_'], ['+', '/'], $tokenParts[1])
            ),
            true
        );
        
        return $payload;
        
    } catch (Exception $e) {
        return false;
    }
}