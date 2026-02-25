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
    public function new(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $projet = new Projet();
        $form   = $this->createForm(ProjetType::class, $projet, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imagesFiles = $form->get('imagesFiles')->getData();

            if (empty($imagesFiles)) {
                $this->addFlash('danger', 'Au moins une image est requise pour créer un projet.');
                return $this->render('admin/gestion_projets/new.html.twig', ['form' => $form]);
            }

            $isFirst = true;
            foreach ($imagesFiles as $imageFile) {
                $nomFichier = $this->saveImage($imageFile);
                if ($nomFichier === null) {
                    $this->addFlash('danger', 'Un fichier uploadé n\'est pas une image valide et a été ignoré.');
                    continue;
                }

                $projetImage = new ProjetImage();
                $projetImage->setNomFichier($nomFichier);
                $projetImage->setIsCover($isFirst);
                $projet->addImage($projetImage);

                $isFirst = false;
            }

            if ($projet->getImages()->isEmpty()) {
                $this->addFlash('danger', 'Aucune image valide n\'a pu être traitée.');
                return $this->render('admin/gestion_projets/new.html.twig', ['form' => $form]);
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
    public function edit(
        Request $request,
        Projet $projet,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(ProjetType::class, $projet, ['is_new' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // ── Suppression des images cochées ────────────────────────────────
            $idsToDelete = $request->request->all('images_to_delete');
            if (!empty($idsToDelete)) {
                foreach ($idsToDelete as $imageId) {
                    $imageId      = (int) $imageId;
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

            // ── Changement de cover ───────────────────────────────────────────
            $newCoverId = $request->request->getInt('cover_image_id');
            if ($newCoverId > 0) {
                foreach ($projet->getImages() as $img) {
                    $img->setIsCover($img->getId() === $newCoverId);
                }
            }

            // ── Ajout de nouvelles images ─────────────────────────────────────
            $newImagesFiles = $form->get('imagesFiles')->getData();
            if (!empty($newImagesFiles)) {
                $hasCover = false;
                foreach ($projet->getImages() as $img) {
                    if ($img->isCover()) {
                        $hasCover = true;
                        break;
                    }
                }

                $isFirstNew = !$hasCover;
                foreach ($newImagesFiles as $imageFile) {
                    $nomFichier = $this->saveImage($imageFile);
                    if ($nomFichier === null) {
                        $this->addFlash('danger', 'Un fichier uploadé n\'est pas une image valide et a été ignoré.');
                        continue;
                    }

                    $projetImage = new ProjetImage();
                    $projetImage->setNomFichier($nomFichier);
                    $projetImage->setIsCover($isFirstNew);
                    $projet->addImage($projetImage);

                    $isFirstNew = false;
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
    public function delete(
        Request $request,
        Projet $projet,
        EntityManagerInterface $em
    ): Response {
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
    public function deleteImage(
        Request $request,
        ProjetImage $projetImage,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $projet = $projetImage->getProjet();

        if ($this->isCsrfTokenValid('delete-image-' . $projetImage->getId(), $request->getPayload()->getString('_token'))) {
            $wasCover = $projetImage->isCover();
            $projetId = $projet->getId();

            $this->deleteImageFile($projetImage->getNomFichier());
            $projet->removeImage($projetImage);
            $em->remove($projetImage);
            $em->flush();

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
     * Sert une image de projet depuis var/projets/ avec autorisation ROLE_ADMIN.
     *
     * Sécurité :
     *  - ROLE_ADMIN obligatoire (+ access_control security.yaml couvre /admin/*)
     *  - Path traversal impossible : l'ID en BDD donne le nom de fichier — jamais de
     *    nom brut dans l'URL ni de paramètre de chemin contrôlable par l'utilisateur
     *  - Content-Type forcé depuis les magic bytes réels (finfo) — jamais depuis l'URL
     *  - DISPOSITION_INLINE pour affichage in-browser
     *  - Cache navigateur 1h (performance sans compromettre la sécurité)
     */
    #[Route('/image/{id}/voir', name: 'app_voir_image_projet_admin', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function voirImageAdmin(ProjetImage $projetImage): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $filePath = $this->getProjectImagesDir() . '/' . $projetImage->getNomFichier();

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Image introuvable.');
        }

        // Content-Type depuis les magic bytes réels (ne fait JAMAIS confiance à l'extension)
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
     * Sauvegarde une image uploadée dans var/projets/ après validation MIME réelle.
     *
     * OWASP A03 : finfo lit les "magic bytes" du fichier temporaire pour déterminer
     * son type réel, indépendamment du Content-Type déclaré par le navigateur.
     * Protège contre les fichiers malveillants renommés (ex: shell.php → photo.jpg).
     *
     * @return string|null Nom du fichier stocké, null si type MIME invalide
     */
    private function saveImage(\Symfony\Component\HttpFoundation\File\UploadedFile $file): ?string
    {
        $finfo    = new \finfo(\FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file->getPathname());

        if (!array_key_exists($mimeType, self::ALLOWED_IMAGE_MIMES)) {
            return null;
        }

        $extension  = self::ALLOWED_IMAGE_MIMES[$mimeType];
        // Nom aléatoire cryptographiquement sûr — jamais le nom original de l'utilisateur
        $nomFichier = bin2hex(random_bytes(16)) . '.' . $extension;

        $file->move($this->getProjectImagesDir(), $nomFichier);

        return $nomFichier;
    }

    /**
     * Supprime le fichier image physique depuis var/projets/ s'il existe.
     */
    private function deleteImageFile(string $nomFichier): void
    {
        $chemin = $this->getProjectImagesDir() . '/' . $nomFichier;
        if (file_exists($chemin)) {
            unlink($chemin);
        }
    }

    /**
     * Retourne le chemin absolu vers var/projets/.
     */
    private function getProjectImagesDir(): string
    {
        return $this->getParameter('projets_images_directory');
    }
}