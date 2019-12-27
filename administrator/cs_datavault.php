<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Cs_datavault
 * @author     Ted Lowe <lists@creativespirits.org>
 * @copyright  2019 (c) Creative Spirits
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\MVC\Controller\BaseController;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;

// Access check.
if (!Factory::getUser()->authorise('core.manage', 'com_cs_datavault'))
{
	throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'));
}

// Include dependancies
jimport('joomla.application.component.controller');

JLoader::registerPrefix('Cs_datavault', JPATH_COMPONENT_ADMINISTRATOR);
JLoader::register('Cs_datavaultHelper', JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'cs_datavault.php');

$controller = BaseController::getInstance('Cs_datavault');
$controller->execute(Factory::getApplication()->input->get('task'));
$controller->redirect();
