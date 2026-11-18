<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use App\Controller\Admin\UserCrudController;
use App\Controller\Admin\VehicleCrudController;
use App\Controller\Admin\TripCrudController;
use App\Controller\Admin\BookingCrudController;
use App\Controller\Admin\ReviewCrudController;
use App\Controller\Admin\ConversationCrudController;
use App\Controller\Admin\MessageCrudController;
use App\Controller\Admin\NotificationCrudController;
use App\Controller\Admin\AdminLogCrudController;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);
        return $this->redirect(
            $adminUrlGenerator->setController(UserCrudController::class)->generateUrl()
        );

        // Option 1. You can make your dashboard redirect to some common page of your backend
        //
        //return $this->redirectToRoute('admin_user_index');

        // Option 2. You can make your dashboard redirect to different pages depending on the user
        //
        // if ('jane' === $this->getUser()->getUsername()) {
        //     return $this->redirectToRoute('...');
        // }

        // Option 3. You can render some custom template to display a proper dashboard with widgets, etc.
        // (tip: it's easier if your template extends from @EasyAdmin/page/content.html.twig)
        //
        // return $this->render('some/path/my-dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('App');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');

        yield MenuItem::section('Utilisateurs');
        yield MenuItem::linkTo(UserCrudController::class, 'Utilisateurs', 'fa fa-users');
        yield MenuItem::linkTo(VehicleCrudController::class, 'Véhicules', 'fa fa-car');

        yield MenuItem::section('Trajets');
        yield MenuItem::linkTo(TripCrudController::class, 'Trajets', 'fa fa-road');
        yield MenuItem::linkTo(BookingCrudController::class, 'Réservations', 'fa fa-ticket');

        yield MenuItem::section('Communauté');
        yield MenuItem::linkTo(ReviewCrudController::class, 'Avis', 'fa fa-star');
        yield MenuItem::linkTo(ConversationCrudController::class, 'Conversations', 'fa fa-comments');
        yield MenuItem::linkTo(MessageCrudController::class, 'Messages', 'fa fa-envelope');
        yield MenuItem::linkTo(NotificationCrudController::class, 'Notifications', 'fa fa-bell');

        yield MenuItem::section('Administration');
        yield MenuItem::linkTo(AdminLogCrudController::class, 'Journal admin', 'fa fa-shield');
    }
}
