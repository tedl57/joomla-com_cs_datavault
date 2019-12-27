<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Cs_datavault
 * @author     Ted Lowe <lists@creativespirits.org>
 * @copyright  2019 (c) Creative Spirits
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use \Joomla\CMS\Factory;
use \Joomla\CMS\MVC\Controller\BaseController;

// Include dependancies
jimport('joomla.application.component.controller');

JLoader::registerPrefix('Cs_datavault', JPATH_COMPONENT);
JLoader::register('Cs_datavaultController', JPATH_COMPONENT . '/controller.php');


// Execute the task.
$controller = BaseController::getInstance('Cs_datavault');
$controller->execute(Factory::getApplication()->input->get('task'));
$controller->redirect();
