<?php
declare( strict_types=1 );
namespace MapaGeoEditor;
class PFGeoLocationInput extends \PFFormInput {
 public static function getName(): string { return 'mapa-geolocalizacao'; }
 public function getResourceModuleNames() { return [ 'ext.mapa.geoEditor' ]; }
 public function getHtmlText(): string {
  $id = 'mapa-geo-' . (int)$this->mInputNumber;
  $html = file_get_contents( __DIR__ . '/widget.html' );
  $html = str_replace( 'id="geo"', 'id="' . $id . '"', $html );
  return $html . \Html::hidden( $this->mInputName, '', [
   'class' => 'mapa-geo-transport', 'data-geo-for' => $id, 'disabled' => $this->mIsDisabled
  ] );
 }
}