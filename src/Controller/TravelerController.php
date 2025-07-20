<?php

namespace App\Controller;

use App\Entity\Traveler;
use App\Form\TravelerType;
use App\Repository\TravelerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/traveler')]
final class TravelerController extends AbstractController
{
    #[Route(name: 'app_traveler_index', methods: ['GET'])]
    public function index(TravelerRepository $travelerRepository): Response
    {
        return $this->render('traveler/index.html.twig', [
            'travelers' => $travelerRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_traveler_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $traveler = new Traveler();
        $form = $this->createForm(TravelerType::class, $traveler);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($traveler);
            $entityManager->flush();

            return $this->redirectToRoute('app_traveler_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('traveler/new.html.twig', [
            'traveler' => $traveler,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_traveler_show', methods: ['GET'])]
    public function show(Traveler $traveler): Response
    {
        return $this->render('traveler/show.html.twig', [
            'traveler' => $traveler,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_traveler_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Traveler $traveler, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TravelerType::class, $traveler);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_traveler_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('traveler/edit.html.twig', [
            'traveler' => $traveler,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_traveler_delete', methods: ['POST'])]
    public function delete(Request $request, Traveler $traveler, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$traveler->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($traveler);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_traveler_index', [], Response::HTTP_SEE_OTHER);
    }
}
