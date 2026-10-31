<?php
// Configuração mínima exclusiva dos testes unitários, sem acesso a banco real.
$wgSitename = 'ArticleGeolocation Unit Tests';
$wgServer = 'http://localhost';
$wgScriptPath = '';
$wgDBtype = 'mysql';
$wgDBserver = '127.0.0.1';
$wgDBname = 'nao_conectar';
$wgDBuser = 'nao_conectar';
$wgDBpassword = '';
$wgSecretKey = str_repeat('0', 64);
wfLoadExtension('ArticleGeolocation');
