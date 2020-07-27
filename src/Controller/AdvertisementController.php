<?php

namespace App\Controller;

use App\Entity\Advertisement;
use App\Form\AdvertisementType;
use App\Entity\AdvertisementFile;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\AdvertisementRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\Admin\AdvertisementType as AdAdminType;
use App\Form\DefaultForm\AdvertisementType as AdDefaultType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * @Route("/admin/regie-publicitaire")
 */
class AdvertisementController extends AbstractController
{
    private $manager;

    private $advertisementRepo;

    public function __construct(EntityManagerInterface $manager, AdvertisementRepository $advertisementRepo)
    {
        $this->manager = $manager;
        $this->advertisementRepo = $advertisementRepo;
    }

    /**
     * @IsGranted("ROLE_ADVERTISER")
     * @Route("/annonces", name="advertisement_index", methods={"GET"})
     */
    public function index(): Response
    {
        // Admin user :
        $advertisements = $this->advertisementRepo->findAll();

        // Advertiser user : 
        $user = $this->getUser();
        $advertisementsByUser = $this->advertisementRepo->findBy(array('user' => $user));

        // dd($advertisementsByUser);

        return $this->render('ad_admin/advertisement/index.html.twig', [
            'advertisements' => $advertisements,
            'advertisementsByUser' => $advertisementsByUser
        ]);
    }

    /**
     * @IsGranted("ROLE_ADVERTISER")
     * @Route("/annonces/creer", name="advertisement_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        $advertisement = new Advertisement();

        $user = $this->getUser();

        if ($user->getRoles() === array('ROLE_ADMIN', 'ROLE_USER')) {
            $form = $this->createForm(AdAdminType::class, $advertisement);
            $form->handleRequest($request);
        }elseif ($user->getRoles() === array('ROLE_ADVERTISER', 'ROLE_USER')) {
            $form = $this->createForm(AdDefaultType::class, $advertisement);
            $form->handleRequest($request);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // Set user app to annonce
            $user = $this->getUser();

            $advertisement->setUser($user);

            // Add advertisement picture file to advertisement
            foreach($advertisement->getAdvertisementFiles() as $advertisementFile) {
                $advertisementFile->setAdvertisement($advertisement);
                $this->manager->persist($advertisementFile);
            }

            $this->manager->persist($advertisement);
            $this->manager->flush();

            $this->addFlash('success', "L'annonce a été ajoutée avec succès");

            return $this->redirectToRoute('advertisement_index');
        }

        if ($user->getRoles() === array('ROLE_ADMIN', 'ROLE_USER')) {
            return $this->render('ad_admin/advertisement/save.html.twig', [
                'advertisement' => $advertisement,
                'form' => $form->createView(),
            ]);
        }elseif ($user->getRoles() === array('ROLE_ADVERTISER', 'ROLE_USER')) {
            return $this->render('ad_admin/advertisement/restricted_save.html.twig', [
                'advertisement' => $advertisement,
                'form' => $form->createView(),
            ]);
        }
    }

    

    /**
     * @Security("is_granted('ROLE_ADMIN')", message="Vous devez être un admin pour consulter cette page")
     * @Route("/annonces/{id}/afficher", name="advertisement_admin_show", methods={"GET"})
     */
    public function showAdAdmin(Advertisement $advertisement): Response
    {
        return $this->render('ad_admin/advertisement/show.html.twig', [
            'advertisement' => $advertisement,
        ]);
    }

    /**
     * @Security("is_granted('ROLE_ADVERTISER') and user === advertisement.getUser()", message="Cette annonce ne vous appartient pas, vous ne pouvez pas la consulter")
     * @Route("/annonces/{id}/consulter", name="advertisement_advertiser_show", methods={"GET"})
     */
    public function showAdAdvertiser(Advertisement $advertisement): Response
    {
        return $this->render('ad_admin/advertisement/show.html.twig', [
            'advertisement' => $advertisement,
        ]);
    }


    /**
     * @Security("is_granted('ROLE_ADMIN')", message="Vous devez être un admin pour consulter cette page")
     * @Route("/annonces/{id}/modifier", name="advertisement_admin_edit", methods={"GET","POST"})
     */
    public function editAdAdmin(Request $request, Advertisement $advertisement): Response
    {
        $user = $this->getUser();

        if ($user->getRoles() === array('ROLE_ADMIN', 'ROLE_USER')) {
            $form = $this->createForm(AdAdminType::class, $advertisement);
            $form->handleRequest($request);
        }
        if ($form->isSubmitted() && $form->isValid()) {

            // Add advertisement picture file to advertisement
            foreach($advertisement->getAdvertisementFiles() as $advertisementFile) {
                $advertisementFile->setAdvertisement($advertisement);
                $this->manager->persist($advertisementFile);
            }

            $this->manager->flush();

            $this->addFlash('success', "L'annonce a été modifiée avec succès");

            return $this->redirectToRoute('advertisement_index');
        }
        return $this->render('ad_admin/advertisement/save.html.twig', [
            'advertisement' => $advertisement,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Security("is_granted('ROLE_ADVERTISER') and user === advertisement.getUser()", message="Cette annonce ne vous appartient pas, vous ne pouvez pas la modifier")
     * @Route("/annonces/{id}/editer", name="advertisement_advertiser_edit", methods={"GET","POST"})
     */
    public function editAdAdvertiser(Request $request, Advertisement $advertisement): Response
    {
        $user = $this->getUser();

        // dd($advertisement->getStatus());

        if ($user->getRoles() === array('ROLE_ADVERTISER', 'ROLE_USER')) {
            if (
                $advertisement->getStatus() == 'Validé' || 
                $advertisement->getStatus() == 'Publié' ||
                $advertisement->getStatus() == 'En pause' ||
                $advertisement->getStatus() == 'Dépublié' ||
                $advertisement->getStatus() == 'Validé et publié'
                ) {
                    $form = $this->createForm(AdvertisementType::class, $advertisement);
                    $form->handleRequest($request);
            }elseif ($advertisement->getStatus() == 'Refusé' || $advertisement->getStatus() == 'En attente de validation') {
                $form = $this->createForm(AdDefaultType::class, $advertisement);
                $form->handleRequest($request);
            }

            if ($form->isSubmitted() && $form->isValid()) {

                // Add advertisement picture file to advertisement
                foreach($advertisement->getAdvertisementFiles() as $advertisementFile) {
                    $advertisementFile->setAdvertisement($advertisement);
                    $this->manager->persist($advertisementFile);
                }
    
                $this->manager->flush();
    
                $this->addFlash('success', "L'annonce a été modifiée avec succès");
    
                return $this->redirectToRoute('advertisement_index');
            }
            if ($user->getRoles() === array('ROLE_ADVERTISER', 'ROLE_USER')) {
                if (
                    $advertisement->getStatus() == 'Validé' || 
                    $advertisement->getStatus() == 'Publié' ||
                    $advertisement->getStatus() == 'En pause' ||
                    $advertisement->getStatus() == 'Dépublié' ||
                    $advertisement->getStatus() == 'Validé et publié'
                    ) {
                    return $this->render('ad_admin/advertisement/save.html.twig', [
                        'advertisement' => $advertisement,
                        'form' => $form->createView(),
                    ]);
                }else {
                    return $this->render('ad_admin/advertisement/restricted_save.html.twig', [
                        'advertisement' => $advertisement,
                        'form' => $form->createView(),
                    ]);
                }
            }
        }
    }

    /**
     * @Security("is_granted('ROLE_ADMIN')", message="Vous devez être un admin pour éxecuter cette fonction")
     * @Route("/annonces/{id}/supprimer", name="advertisement_admin_delete", methods={"DELETE"})
     */
    public function deleteAdAdmin(Request $request, Advertisement $advertisement): Response
    {
        if ($this->isCsrfTokenValid('delete'.$advertisement->getId(), $request->request->get('_token'))) {

            // Remove advertisement picture file to advertisement
            foreach($advertisement->getAdvertisementFiles() as $advertisementFile) {
                $advertisement->removeAdvertisementFile($advertisementFile);
                $this->manager->remove($advertisementFile);
            }

            // Remove Add
            $this->manager->remove($advertisement);
            $this->manager->flush();
            $this->addFlash('success', "L'annonce a été supprimée avec succès");
        }

        return $this->redirectToRoute('advertisement_index');
    }

    /**
     * @Security("is_granted('ROLE_ADVERTISER') and user === advertisement.getUser()", message="Cette annonce ne vous appartient pas, vous ne pouvez pas la modifier")
     * @Route("/annonces/{id}/effacer", name="advertisement_advertiser_delete", methods={"DELETE"})
     */
    public function deleteAdAdvertiser(Request $request, Advertisement $advertisement): Response
    {
        if ($this->isCsrfTokenValid('delete'.$advertisement->getId(), $request->request->get('_token'))) {

            // Remove advertisement picture file to advertisement
            foreach($advertisement->getAdvertisementFiles() as $advertisementFile) {
                $advertisement->removeAdvertisementFile($advertisementFile);
                $this->manager->remove($advertisementFile);
            }

            // Remove Add
            $this->manager->remove($advertisement);
            $this->manager->flush();
            $this->addFlash('success', "L'annonce a été supprimée avec succès");
        }

        return $this->redirectToRoute('advertisement_index');
    }
}
