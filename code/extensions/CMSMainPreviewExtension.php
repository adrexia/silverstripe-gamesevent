<?php
/**
 * Extension to disable the preview on pages that don't need it.
 * To hide the preview on a page, add this to the class:
 *
 *    private static $hide_preview_panel = true;
 *
 * @package gamesevent
 */
class CMSMainPreviewExtension extends Extension {
	public function updateEditForm($form) {
		$classNameField = $form->Fields()->dataFieldByName('ClassName');
		if ($classNameField) {
			$className = $classNameField->Value();
			if ($className && class_exists($className) && $className::config()->hide_preview_panel) {
				$form->Fields()->removeByName(array('SilverStripeNavigator'));
				$form->removeExtraClass('cms-previewable');
			}
		}
	}
}
