<?php

namespace App\Controller;

use App\Entity\PreviousPasswords;
use App\Repository\GradeRepository;
use App\Entity\Student;
use App\Form\StudentType;
use App\Repository\StudentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/etudiant')]
class StudentController extends AbstractController
{
    #[Route('/', name: 'app_student_index', methods: ['GET'])]
    public function index(StudentRepository $studentRepository): Response
    {
        return $this->render('student/index.html.twig', [
            'students' => $studentRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_student_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $userPasswordHasher): Response
    {
        $student = new Student();
        $form = $this->createForm(StudentType::class, $student);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $student->setRoles(['ROLE_STUDENT']);
            $student->setPassword(
                $userPasswordHasher->hashPassword(
                    $student,
                    $form->get('password')->getData()
                )
            );

            //encode the plain password for saving it as a previous password
            $previousPassword = new PreviousPasswords($student);
            $previousPassword->setPassword(
                $userPasswordHasher->hashPassword(
                    $previousPassword,
                    $form->get('password')->getData()
                )
            );
            $student->addPreviousPasswords($previousPassword);

            $entityManager->persist($student);
            $entityManager->persist($previousPassword);
            $entityManager->flush();


            return $this->redirectToRoute('app_student_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('student/new.html.twig', [
            'student' => $student,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_student_show', methods: ['GET'])]
    public function show(Student $student): Response
    {
        return $this->render('student/show.html.twig', [
            'student' => $student,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_student_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Student $student, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(StudentType::class, $student);
        $form->remove('password');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_student_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('student/edit.html.twig', [
            'student' => $student,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_student_delete', methods: ['POST'])]
    public function delete(Request $request, Student $student, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$student->getId(), $request->request->get('_token'))) {
            $entityManager->remove($student);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_student_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/notes', name: 'app_student_notes', methods: ['GET', 'POST'])]
    public function notes(Student $student, GradeRepository $gradeRepository)
    {
        $allGrades = $gradeRepository->findStudentGradesWithRelations($student);
        $groupedGrades = $gradeRepository->getGroupedGradesWithAverages($allGrades);

        return $this->render('evaluation/index.html.twig', [
            'grouped_grades' => $groupedGrades,
            'student' => $student,
        ]);
    }
}
