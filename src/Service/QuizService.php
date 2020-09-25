<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Quiz;
use App\Entity\Result;
use App\Entity\User;
use App\Repository\QuizRepository;
use App\Repository\ResultRepository;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

class QuizService
{
    private const  BUNCH_SIZE = 100;
    private const MAX_RESULT = 3;
    private const PAGINATION_QUIZES_LIMIT = 8;
    private const PAGINATION_LEADERS_LIMIT = 8;
    private QuizRepository $quizRepository;
    private ResultRepository $resultRepository;
    private PaginatorInterface $paginator;

    /**
     * QuizService constructor.
     * @param QuizRepository $quizRepository
     * @param ResultRepository $resultRepository
     * @param PaginatorInterface $paginator
     */
    public function __construct
    (
        QuizRepository $quizRepository,
        ResultRepository $resultRepository,
        PaginatorInterface $paginator
    )
    {
        $this->quizRepository = $quizRepository;
        $this->resultRepository = $resultRepository;
        $this->paginator = $paginator;
    }

    public function getPaginateQuizes(int $page): PaginationInterface
    {
        return $this
            ->paginator
            ->paginate($this->quizRepository->findNext(), $page, self::PAGINATION_QUIZES_LIMIT);
    }

    public function getPaginateQuizesWithSearchCriteria(int $page, string $search): PaginationInterface
    {
        return $this
            ->paginator
            ->paginate($this->quizRepository->search($search), $page, self::PAGINATION_QUIZES_LIMIT);
    }

    public function getLeadersForPage(PaginationInterface $pagination): array
    {
        $result = [];
        /** @var Quiz $quiz */
        foreach ($pagination->getItems() as $quiz) {
            $result[$quiz->getId()] = $this
                ->resultRepository
                ->getLeaders($quiz)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        }

        return $result;
    }

    public function getUserPlace(User $user, Quiz $quiz): int
    {
        $i = 1;
        while (true) {
            $results = [];
            $it = $this
                ->paginator
                ->paginate($this->resultRepository->getLeaders($quiz), $i, self::BUNCH_SIZE)
                ->getItems();
            array_push($results, ...$it);
            for ($k = 0; $k < count($results); $k++) {
                if ($results[$k]->getUser() === $user) {
                    return $k + 1;
                }
            }
            $i++;
        }
    }

    function maxResult(float $max, Result $r): float
    {
        return $max >= $r->getResult() ? $max : $r->getResult();
    }

    function minDuration(int $min, Result $r)
    {
        if (!$r->getEndDate()) {
            return $min;
        }
        $duration = $r->getEndDate()->getTimestamp() - $r->getStartDate()->getTimestamp();

        return $min <= $duration ? $min : $duration;
    }

    /**
     * @param User $user
     * @param Quiz $quiz
     * @return Result|null
     */
    public function getResult(User $user, Quiz $quiz): ?Result
    {
        return $this->resultRepository->findOneBy(['user' => $user, 'quiz' => $quiz]);
    }

    public function getTopLeaders(Quiz $quiz): array
    {
        $qb = $this->resultRepository->getLeaders($quiz);

        return $qb
            ->setMaxResults(self::MAX_RESULT)
            ->getQuery()
            ->getResult();
    }

    public function getPaginateLeaders(Quiz $quiz, int $page): PaginationInterface
    {
        $query = $this->resultRepository->getLeaders($quiz)->getQuery();

        return $this->paginator->paginate($query, $page, self::PAGINATION_LEADERS_LIMIT);
    }
}