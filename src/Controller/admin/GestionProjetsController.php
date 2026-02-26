<?php

namespace App\Controller\admin;

use App\Entity\Projet;
use App\Entity\ProjetImage;
use App\Form\ProjetType;
use App\Repository\ProjetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/gestion-projets')]
final class GestionProjetsController extends AbstractController
{
    /**
     * Types MIME acceptés pour les images de projet.
     * Validation double : contrainte Symfony (Form) + finfo magic bytes (OWASP A03).
     */
    private const ALLOWED_IMAGE_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    // ── INDEX ────────────────────────────────────────────────────────────────

    #[Route('', name: 'app_gestion_projets', methods: ['GET'])]
    public function index(ProjetRepository $projetRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/gestion_projets/index.html.twig', [
            'projets' => $projetRepository->findAllWithImages(),
        ]);
    }

    // ── NEW ──────────────────────────────────────────────────────────────────

    #[Route('/nouveau', name: 'app_new_gestion_projets', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $projet = new Projet();
        $form   = $this->createForm(ProjetType::class, $projet, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // ── Cover (obligatoire à la création) ────────────────────────────
            $coverFile = $form->get('coverFile')->getData();
            if ($coverFile) {
                $nomFichier = $this->saveImage($coverFile);
                if ($nomFichier === null) {
                    $this->addFlash('danger', 'L\'image de couverture n\'est pas valide (JPEG, PNG ou WebP requis).');
                    return $this->render('admin/gestion_projets/new.html.twig', ['form' => $form]);
                }
                $cover = new ProjetImage();
                $cover->setNomFichier($nomFichier);
                $cover->setIsCover(true);
                $projet->addImage($cover);
            }

            // ── Images carousel (optionnelles) ────────────────────────────────
            $carouselFiles = $form->get('carouselFiles')->getData();
            if (!empty($carouselFiles)) {
                foreach ($carouselFiles as $file) {
                    $nomFichier = $this->saveImage($file);
                    if ($nomFichier === null) {
                        $this->addFlash('danger', 'Une image du carousel n\'est pas valide et a été ignorée.');
                        continue;
                    }
                    $image = new ProjetImage();
                    $image->setNomFichier($nomFichier);
                    $image->setIsCover(false);
                    $projet->addImage($image);
                }
            }

            $em->persist($projet);
            $em->flush();

            $this->addFlash('success', 'Projet « ' . $projet->getTitre() . ' » créé avec succès.');
            return $this->redirectToRoute('app_show_gestion_projets', ['id' => $projet->getId()]);
        }

        return $this->render('admin/gestion_projets/new.html.twig', ['form' => $form]);
    }

    // ── SHOW ─────────────────────────────────────────────────────────────────

    #[Route('/{id}', name: 'app_show_gestion_projets', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Projet $projet): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/gestion_projets/show.html.twig', ['projet' => $projet]);
    }

    // ── EDIT ─────────────────────────────────────────────────────────────────

    #[Route('/{id}/modifier', name: 'app_edit_gestion_projets', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Projet $projet, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(ProjetType::class, $projet, ['is_new' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // ── Suppression des images cochées ────────────────────────────────
            $idsToDelete = $request->request->all('images_to_delete');
            if (!empty($idsToDelete)) {
                foreach ($idsToDelete as $imageId) {
                    $imageId       = (int) $imageId;
                    $imageToDelete = $projet->getImages()->filter(
                        fn(ProjetImage $img) => $img->getId() === $imageId
                    )->first();

                    if ($imageToDelete) {
                        $this->deleteImageFile($imageToDelete->getNomFichier());
                        $projet->removeImage($imageToDelete);
                        $em->remove($imageToDelete);
                    }
                }
            }

            // ── Remplacement de la cover ──────────────────────────────────────
            $coverFile = $form->get('coverFile')->getData();
            if ($coverFile) {
                $nomFichier = $this->saveImage($coverFile);
                if ($nomFichier !== null) {
                    // Retirer l'ancienne cover
                    foreach ($projet->getImages() as $img) {
                        if ($img->isCover()) {
                            $this->deleteImageFile($img->getNomFichier());
                            $projet->removeImage($img);
                            $em->remove($img);
                            break;
                        }
                    }
                    // Ajouter la nouvelle
                    $newCover = new ProjetImage();
                    $newCover->setNomFichier($nomFichier);
                    $newCover->setIsCover(true);
                    $projet->addImage($newCover);
                } else {
                    $this->addFlash('danger', 'L\'image de couverture fournie n\'est pas valide et a été ignorée.');
                }
            }

            // ── Ajout d'images carousel ───────────────────────────────────────
            $carouselFiles = $form->get('carouselFiles')->getData();
            if (!empty($carouselFiles)) {
                foreach ($carouselFiles as $file) {
                    $nomFichier = $this->saveImage($file);
                    if ($nomFichier === null) {
                        $this->addFlash('danger', 'Une image du carousel n\'est pas valide et a été ignorée.');
                        continue;
                    }
                    $image = new ProjetImage();
                    $image->setNomFichier($nomFichier);
                    $image->setIsCover(false);
                    $projet->addImage($image);
                }
            }

            // ── Garantie d'une cover si des images existent ───────────────────
            if ($projet->getImages()->count() > 0 && $projet->getCoverImage() === null) {
                $projet->getImages()->first()->setIsCover(true);
            }

            $em->flush();

            $this->addFlash('success', 'Projet « ' . $projet->getTitre() . ' » modifié avec succès.');
            return $this->redirectToRoute('app_show_gestion_projets', ['id' => $projet->getId()]);
        }

        return $this->render('admin/gestion_projets/edit.html.twig', [
            'form'   => $form,
            'projet' => $projet,
        ]);
    }

    // ── DELETE ───────────────────────────────────────────────────────────────

    #[Route('/{id}/supprimer', name: 'app_delete_gestion_projets', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Projet $projet, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete-projet-' . $projet->getId(), $request->getPayload()->getString('_token'))) {
            foreach ($projet->getImages() as $image) {
                $this->deleteImageFile($image->getNomFichier());
            }
            $em->remove($projet);
            $em->flush();
            $this->addFlash('success', 'Projet « ' . $projet->getTitre() . ' » supprimé avec succès.');
        } else {
            $this->addFlash('danger', 'Action non autorisée : token de sécurité invalide.');
        }

        return $this->redirectToRoute('app_gestion_projets');
    }

    // ── DELETE IMAGE individuelle ─────────────────────────────────────────────

    #[Route('/image/{id}/supprimer', name: 'app_delete_projet_image', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deleteImage(Request $request, ProjetImage $projetImage, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $projet = $projetImage->getProjet();

        if ($this->isCsrfTokenValid('delete-image-' . $projetImage->getId(), $request->getPayload()->getString('_token'))) {
            $wasCover = $projetImage->isCover();
            $projetId = $projet->getId();

            $this->deleteImageFile($projetImage->getNomFichier());
            $projet->removeImage($projetImage);
            $em->remove($projetImage);
            $em->flush();

            // Si la cover a été supprimée, désigner la première image restante
            if ($wasCover && $projet->getImages()->count() > 0) {
                $projet->getImages()->first()->setIsCover(true);
                $em->flush();
            }

            $this->addFlash('success', 'Image supprimée avec succès.');
            return $this->redirectToRoute('app_show_gestion_projets', ['id' => $projetId]);
        }

        $this->addFlash('danger', 'Action non autorisée : token de sécurité invalide.');
        return $this->redirectToRoute('app_show_gestion_projets', ['id' => $projet->getId()]);
    }

    // ── SERVE IMAGE (admin) ───────────────────────────────────────────────────

    /**
     * Sert une image depuis var/projets/ — réservé aux admins.
     * Path traversal impossible : chemin résolu via l'ID en BDD.
     * Content-Type forcé depuis les magic bytes réels (finfo).
     */
    #[Route('/image/{id}/voir', name: 'app_voir_image_projet_admin', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function voirImageAdmin(ProjetImage $projetImage): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $filePath = $this->getProjectImagesDir() . '/' . $projetImage->getNomFichier();

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Image introuvable.');
        }

        $finfo = new \finfo(\FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($filePath) ?: 'application/octet-stream';

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);
        $response->headers->set('Content-Type', $mime);
        $response->setMaxAge(3600);
        $response->setPublic();

        return $response;
    }

    // ── HELPERS ──────────────────────────────────────────────────────────────

    /**
     * Sauvegarde une image uploadée après validation MIME réelle (magic bytes).
     * OWASP A03 : protège contre les fichiers malveillants renommés.
     *
     * @return string|null Nom du fichier stocké, null si MIME invalide
     */
    private function saveImage(\Symfony\Component\HttpFoundation\File\UploadedFile $file): ?string
    {
        $finfo    = new \finfo(\FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file->getPathname());

        if (!array_key_exists($mimeType, self::ALLOWED_IMAGE_MIMES)) {
            return null;
        }

        $extension  = self::ALLOWED_IMAGE_MIMES[$mimeType];
        $nomFichier = bin2hex(random_bytes(16)) . '.' . $extension;

        $file->move($this->getProjectImagesDir(), $nomFichier);

        return $nomFichier;
    }

    private function deleteImageFile(string $nomFichier): void
    {
        $chemin = $this->getProjectImagesDir() . '/' . $nomFichier;
        if (file_exists($chemin)) {
            unlink($chemin);
        }
    }

    private function getProjectImagesDir(): string
    {
        return $this->getParameter('projets_images_directory');
    }
}