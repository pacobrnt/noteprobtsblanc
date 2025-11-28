<?php

namespace App\Repository;

use App\Entity\Grade;
use App\Entity\Student;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Grade>
 *
 * @method Grade|null find($id, $lockMode = null, $lockVersion = null)
 * @method Grade|null findOneBy(array $criteria, array $orderBy = null)
 * @method Grade[]    findAll()
 * @method Grade[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GradeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Grade::class);
    }

    /**
     * Récupère toutes les notes d'un étudiant en faisant les jointures nécessaires.
     * Cette méthode sera adaptée plus tard pour implémenter le filtre US2 (dateAffichageNotes).
     * @return Grade[]
     */
    public function findStudentGradesWithRelations(Student $student): array
    {
        return $this->createQueryBuilder('g')
            // Jointure sur l'évaluation (obligatoire pour accéder à la matière)
            ->join('g.evaluation', 'e')
            // Jointure sur la matière (obligatoire pour le regroupement US1)
            ->join('e.subject', 's')
            // Condition : filtrer par l'étudiant
            ->andWhere('g.student = :studentId')
            ->setParameter('studentId', $student->getId())
            ->orderBy('s.label', 'ASC') // Optionnel, mais améliore la lisibilité avant regroupement
            ->getQuery()
            ->getResult()
            ;
    }


    /**
     * Implémentation US1 : Regroupe les notes fournies par matière et calcule la moyenne.
     * @param Grade[] $grades Collection d'objets Grade (doit inclure Evaluation et Subject).
     * @return array Tableau structuré par matière (name, average, notes).
     */
    public function getGroupedGradesWithAverages(array $grades): array
    {
        $groupedGrades = [];

        // Premier parcours pour regrouper et sommer
        foreach ($grades as $grade) {
            $evaluation = $grade->getEvaluation();
            $subject = $evaluation->getSubject();

            $subjectId = $subject->getId();
            $gradeValue = $grade->getGrade(); // Récupère la note (peut être null ou une string vide)

            // *** CORRECTION DE LA VIOLATION D'ACCÈS ICI ***
            // 1. Convertir la valeur en float et gérer les cas null/vide.
            //    Si $gradeValue est null ou "", (float) le convertira en 0.0, évitant le crash.
            $gradeNumeric = (float) $gradeValue;

            // 2. Continuer uniquement si l'étudiant a été évalué (note non nulle après conversion)
            //    (Optionnel, mais plus sûr pour les notes non-saisies)
            // if ($gradeNumeric === 0.0 && ($gradeValue === null || $gradeValue === '')) {
            //     continue; // Optionnel: ignorer les notes non saisies
            // }


            if (!isset($groupedGrades[$subjectId])) {
                $groupedGrades[$subjectId] = [
                    'id' => $subjectId,
                    'name' => $subject->getLabel(),
                    'totalSum' => 0,
                    'count' => 0,
                    'notes' => []
                ];
            }

            // Utiliser la valeur numérique sécurisée pour l'addition
            $groupedGrades[$subjectId]['totalSum'] += $gradeNumeric;
            $groupedGrades[$subjectId]['count']++;
            $groupedGrades[$subjectId]['notes'][] = $grade;
        }

        // Deuxième parcours pour calculer la moyenne
        foreach ($groupedGrades as &$subjectData) {
            if ($subjectData['count'] > 0) {
                $subjectData['average'] = round($subjectData['totalSum'] / $subjectData['count'], 2);
            } else {
                $subjectData['average'] = 0.0;
            }

            unset($subjectData['totalSum'], $subjectData['count']);
        }

        return array_values($groupedGrades);
    }
}
