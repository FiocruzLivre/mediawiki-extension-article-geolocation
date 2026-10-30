<?php
declare( strict_types=1 );
namespace MapaGeoEditor;
final class Hooks {
 public static function onFormPrinterSetup( $printer ): bool {
  $printer->registerInputType( PFGeoLocationInput::class );
  return true;
 }
}