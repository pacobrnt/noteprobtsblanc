<?php

namespace App\Entity;

use App\Repository\StudentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StudentRepository::class)]
class Student extends User
{
    #[ORM\OneToMany(mappedBy: 'student', targetEntity: Grade::class, orphanRemoval: true)]
    private Collection $grades;

    #[ORM\ManyToOne(fetch: 'EAGER', inversedBy: 'students')]
    #[ORM\JoinColumn(nullable: true)]
    private ?ClassLevel $classLevel = null;

    public function __construct()
    {
        parent::__construct();
        $this->grades = new ArrayCollection();
    }

    /**
     * @return Collection<int, Grade>
     */
    public function getGrades(): Collection
    {
        return $this->grades;
    }

    public function addGrade(Grade $grade): static
    {
        if (!$this->grades->contains($grade)) {
            $this->grades->add($grade);
            $grade->setStudent($this);
        }

        return $this;
    }

    public function removeGrade(Grade $grade): static
    {
        if ($this->grades->removeElement($grade)) {
            // set the owning side to null (unless already changed)
            if ($grade->getStudent() === $this) {
                $grade->setStudent(null);
            }
        }

        return $this;
    }

    public function getClassLevel(): ?ClassLevel
    {
        return $this->classLevel;
    }

    public function setClassLevel(?ClassLevel $classLevel): static
    {
        $this->classLevel = $classLevel;

        return $this;
    }

    public function getGradeByEval (Evaluation $evaluation): ?Grade
    {
        foreach ($this->getGrades() as $grade){
            if ($grade->getEvaluation() === $evaluation){
                return $grade;
            }
        }
        return null;
    }

    /**
     * Réalise l'agrégation et le calcul de la moyenne par matière.
     * ATTENTION : Ceci est un contournement pour l'épreuve. Cette logique devrait être dans un Service/Repository.
     * @return array Tableau des notes regroupées par matière.
     */
    public function getGroupedGradesWithAverages(): array
    {
        $groupedGrades = [];

        // Le getGrades() est la collection récupérée par Doctrine
        foreach ($this->grades as $grade) {
            // Assurez-vous que les relations sont chargées (Eager Fetching ou jointure préalable)
            $evaluation = $grade->getEvaluation();
            $subject = $evaluation->getSubject();

            $subjectId = $subject->getId();
            $gradeValue = $grade->getGrade();

            // Sécurisation du calcul
            $gradeNumeric = (float) $gradeValue;

            if (!isset($groupedGrades[$subjectId])) {
                $groupedGrades[$subjectId] = [
                    'name' => $subject->getLabel(),
                    'totalSum' => 0,
                    'count' => 0,
                    'notes' => []
                ];
            }

            $groupedGrades[$subjectId]['totalSum'] += $gradeNumeric;
            $groupedGrades[$subjectId]['count']++;
            $groupedGrades[$subjectId]['notes'][] = $grade;
        }

        // Calcul des moyennes
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
