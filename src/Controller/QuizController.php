<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Answer;
use App\Entity\Progress;
use App\Entity\Quiz;
use App\Entity\Result;
use App\Entity\User;
use App\Form\QuizProcessFormType;
use App\Service\QuizService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class QuizController
 * @IsGranted("ROLE_USER")
 */
class QuizController extends AbstractController
{
    private QuizService $service;
    private EntityManagerInterface $em;

    /**
     * QuizController constructor.
     * @param QuizService $service
     * @param EntityManagerInterface $em
     */
    public function __construct(QuizService $service, EntityManagerInterface $em)
    {
        $this->service = $service;
        $this->em = $em;
    }

    /**
     * @Route("/quizes", name="app_quizes")
     * @param Request $request
     * @return Response
     */
    public function listAction(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $searchCriteria = $request->query->get('search');

        $pagination = $searchCriteria
            ? $this->service->getPaginateQuizesWithSearchCriteria($page, $searchCriteria)
            : $this->service->getPaginateQuizes($page);
        $leaders = $this->service->getLeadersForPage($pagination);

        return $this->render
        (
            'quiz/all_quizes.html.twig',
            ['pagination' => $pagination, 'leaders' => $leaders]
        );
    }

    /**
     * @Route ("/quiz_info/{id}", name="app_quiz_info")
     * @param Quiz $quiz
     * @return Response
     */
    public function quizInfo(Quiz $quiz): Response
    {
        $result = $this->service->getResult($this->getUser(), $quiz);
        $topResults = $this->service->getTopLeaders($quiz);

        $rate = $result && $result->getEndDate()?  $this->service->getUserPlace($this->getUser(), $quiz):null;

        return $this->render('quiz/quiz_info.html.twig', [
            'quiz' => $quiz,
            'topResults' => $topResults,
            'result' => $result,
            'rate' => $rate,
        ]);
    }

    /**
     * @Route ("/quiz_info/{id}/leaders", name="app_quiz_leaders")
     * @param Quiz $quiz
     * @param Request $request
     * @return Response
     */
    public function leaderBoard(Quiz $quiz, Request $request)
    {
        $page = $request->query->getInt('page', 1);
        $pagination = $this->service->getPaginateLeaders($quiz, $page);

        return $this->render('quiz/leaderboard.html.twig', [
            'quiz' => $quiz,
            'pagination' => $pagination
        ]);
    }

    /**
     * @Route ("/quiz/{id}", name="app_quiz")
     * @param Quiz $quiz
     * @param Request $request
     * @return Response
     */
    public function proceed(Quiz $quiz, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $result = $this->service->getResult($user, $quiz);

        if (!($quiz->getIsActive()) || ($result) && $result->getEndDate()) {
            return $this->redirectToRoute('app_quiz_info', ['id' => $quiz->getId()]);
        }

        if (!$result) {
            $result = new Result();
            $result->setStartDate(new DateTime());
            $quiz->addResult($result);
            $user->addResult($result);
            $this->em->persist($user);
            $this->em->persist($result);
            $this->em->persist($quiz);
            $this->em->flush();
        }

        foreach ($quiz->getQuestions() as $question) {
            $flag = false;
            foreach ($result->getProgress() as $progress) {
                if ($progress->getQuestion() === $question) {
                    $flag = true;
                    break;
                }
            }

            if (!$flag) {
                $form = $this->createForm(QuizProcessFormType::class, null, ['question' => $question]);
                $form->handleRequest($request);

                if ($form->isSubmitted() && $form->isValid()) {
                    /** @var Answer $answer */
                    $answer = ($form->getData())['answer'];
                    $progress = (new Progress())->setQuestion($question)->setIsRight($answer->getIsRight());
                    if ($answer->getIsRight()) {
                        $result->setResult($result->getResult() + 1);
                    }
                    $result->addProgress($progress);
                    $this->em->persist($progress);
                    $this->em->persist($result);
                    $this->em->flush();

                    $request->getSession()->set('question', $question->getName());
                    $request->getSession()->set('answer', $answer->getName());
                    $request->getSession()->set('isRight', $answer->getIsRight());
                    $request->getSession()->set('progress', $result->getProgress()->count());

                    return $this->redirectToRoute('app_show_answer', [
                        'id' => $quiz->getId(),
                    ]);
                }
                foreach ($question->getAnswers() as $ans) {
                    if ($ans->getIsRight()) {
                        $rightAnswer = $ans->getId();
                        break;
                    }
                }

                return $this->render('quiz/proceed.html.twig', [
                    'form' => $form->createView(),
                    'quiz' => $quiz,
                    'result' => $result,
                    'rightAnswer' => $rightAnswer,
                    'question' => $question]);
            }
        }
        $result->setEndDate(new DateTime());
        $this->em->persist($result);
        $this->em->flush();

        return $this->redirectToRoute('app_quiz_info', ['id' => $quiz->getId()]);
    }

    /**
     * @Route ("/quiz/{id}/answer", name = "app_show_answer")
     * @param Quiz $quiz
     * @param Request $request
     * @return Response
     */
    public function showAnswer(Quiz $quiz, Request $request): Response
    {
        $session = $request->getSession();
        $question = $session->remove('question');
        $answer = $session->remove('answer');
        $progress = $session->remove('progress');
        $isRight = $session->remove('isRight');
        if (!$question || !$answer || $isRight === null) {
            return $this->redirectToRoute('app_quiz', ['id' => $quiz->getId()]);
        }

        return $this->render('quiz/proceed_answer.html.twig', [
            'question' => $question,
            'quiz' => $quiz,
            'answer' => $answer,
            'isRight' => $isRight,
            'progress' => $progress
        ]);
    }
}