<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Cs_datavault
 * @author     Ted Lowe <lists@creativespirits.org>
 * @copyright  2019 (c) Creative Spirits
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access.
defined('_JEXEC') or die;

/**
 * Collects list controller class.
 *
 * @since  1.6
 */
class Cs_datavaultControllerCollects extends Cs_datavaultController
{
	/**
	 * Proxy for getModel.
	 *
	 * @param   string  $name    The model name. Optional.
	 * @param   string  $prefix  The class prefix. Optional
	 * @param   array   $config  Configuration array for model. Optional
	 *
	 * @return object	The model
	 *
	 * @since	1.6
	 */
	public function &getModel($name = 'Collects', $prefix = 'Cs_datavaultModel', $config = array())
	{
		$model = parent::getModel($name, $prefix, array('ignore_request' => true));

		return $model;
	}
}
