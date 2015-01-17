<?php

namespace RbComment\View\Helper;

use Zend\View\Helper\AbstractHelper;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\ServiceLocatorAwareInterface;

class Comment extends AbstractHelper implements ServiceLocatorAwareInterface
{
    private $serviceLocator;

    protected $themes = array(
        'default'    => true,
        'uikit'      => true,
        'bootstrap3' => true,
    );

    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;

        return $this;
    }

    public function getServiceLocator()
    {
        return $this->serviceLocator;
    }

    public function __invoke($theme = 'default')
    {
        // If using a custom theme/partial do not append the prefix
        $invokablePartial = isset($this->themes[$theme])
            ? 'rbcomment/theme/' . $theme
            : $theme;

        $serviceManager = $this->getServiceLocator()->getServiceLocator();
        $viewHelperManager = $serviceManager->get('viewhelpermanager');
        $config = $serviceManager->get('Config');

        $threadRoutes = $config['rb_comment']['thread_routes'];
        $routeMatch = $serviceManager->get('Application')->getMvcEvent()->getRouteMatch();
        $routeName = $routeMatch->getMatchedRouteName();
        $controllerName = $routeMatch->getParam('controller');
        $actionName = $routeMatch->getParam('action');
        $uri = $serviceManager->get('router')->getRequestUri()->getPath();

        if (empty($threadRoutes)){
            //There are no thread routes
            $thread = sha1($uri);
        }else{
            //There are thread route
            if (empty($threadRoutes[$routeName])){
                //But not for this route
                $thread = sha1($uri);
            }else{
                if (is_array($threadRoutes[$routeName])){
                    //This route thread contains controller routes
                    if (empty($threadRoutes[$routeName][$controllerName])){
                        //But not for this controller
                        $thread = sha1($uri);
                    }else{
                        //etc
                        if(is_array($threadRoutes[$routeName][$controllerName])){
                            if (empty($threadRoutes[$routeName][$controllerName][$actionName])){
                                $thread = sha1($uri);
                            }else{
                                $thread = sha1("route/$routeName/controller/$controllerName/action/$actionName");
                            }

                        }else{
                            $thread = sha1("route/$routeName/controller/$controllerName");
                        }
                    }
                }else{
                    //One thread for whole route
                    $thread = sha1("route/$routeName");
                }
            }
        }



        $validationMessages = $viewHelperManager->get('flashMessenger')
                                                ->getMessagesFromNamespace('RbComment');


        $commentTable = $serviceManager->get('RbComment\Model\CommentTable');
        $commentTable->setDateFormat($config['rb_comment']['date_format']);

        echo $viewHelperManager->get('partial')->__invoke($invokablePartial, array(
            'comments' => $commentTable->fetchAllForThread($thread),
            'form' => new \RbComment\Form\CommentForm(),
            'thread' => $thread,
            'validationResults' => count($validationMessages) > 0
                ? json_decode(array_shift($validationMessages))
                : array(),
            'uri' => $uri,
            'zfc_user'=> $config['rb_comment']['zfc_user']['enabled'],
            'gravatar'=> $config['rb_comment']['gravatar']['enabled'],
        ));
    }
}
