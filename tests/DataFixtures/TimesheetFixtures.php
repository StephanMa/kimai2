<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\DataFixtures;

use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\Tag;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Entity\UserPreference;
use App\Timesheet\Util;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

/**
 * Defines the sample data to load in during controller tests.
 */
final class TimesheetFixtures implements TestFixture
{
    /**
     * @var User
     */
    private $user;
    /**
     * @var int
     */
    private $amount = 0;
    /**
     * @var int
     */
    private $running = 0;
    /**
     * @var Activity[]
     */
    private $activities = [];
    /**
     * @var Project[]
     */
    private $projects = [];
    /**
     * @var \DateTime
     */
    private $startDate;
    /**
     * @var \DateTime
     */
    private $fixedStartDate;
    /**
     * @var bool
     */
    private $fixedRate = false;
    /**
     * @var callable
     */
    private $callback;
    /**
     * @var bool
     */
    private $hourlyRate = false;
    /**
     * @var bool
     */
    private $allowEmptyDescriptions = true;
    /**
     * @var bool
     */
    private $exported = false;
    /**
     * @var bool
     */
    private $useTags = false;
    /**
     * @var array
     */
    private $tags = [];

    public function __construct(?User $user = null, ?int $amount = null)
    {
        if ($user !== null) {
            $this->setUser($user);
        }
        if ($amount !== null) {
            $this->setAmount($amount);
        }
    }

    public function setAllowEmptyDescriptions(bool $allowEmptyDescriptions): TimesheetFixtures
    {
        $this->allowEmptyDescriptions = $allowEmptyDescriptions;

        return $this;
    }

    public function setExported(bool $exported): TimesheetFixtures
    {
        $this->exported = $exported;

        return $this;
    }

    public function setFixedRate(bool $fixedRate): TimesheetFixtures
    {
        $this->fixedRate = $fixedRate;

        return $this;
    }

    public function setHourlyRate(bool $hourlyRate): TimesheetFixtures
    {
        $this->hourlyRate = $hourlyRate;

        return $this;
    }

    /**
     * @param string|\DateTime $date
     * @return TimesheetFixtures
     */
    public function setStartDate($date): TimesheetFixtures
    {
        if (!($date instanceof \DateTime)) {
            $date = new \DateTime($date);
        }
        $this->startDate = $date;

        return $this;
    }

    public function setFixedStartDate(\DateTime $date): TimesheetFixtures
    {
        $this->fixedStartDate = $date;

        return $this;
    }

    public function setAmountRunning(int $amount): TimesheetFixtures
    {
        $this->running = $amount;

        return $this;
    }

    public function setAmount(int $amount): TimesheetFixtures
    {
        $this->amount = $amount;

        return $this;
    }

    public function setUser(User $user): TimesheetFixtures
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @param Activity[] $activities
     * @return TimesheetFixtures
     */
    public function setActivities(array $activities): TimesheetFixtures
    {
        $this->activities = $activities;

        return $this;
    }

    /**
     * @param Project[] $projects
     * @return TimesheetFixtures
     */
    public function setProjects(array $projects): TimesheetFixtures
    {
        $this->projects = $projects;

        return $this;
    }

    public function setUseTags(bool $useTags): TimesheetFixtures
    {
        $this->useTags = $useTags;

        return $this;
    }

    /**
     * @param string[] $tags
     * @return TimesheetFixtures
     */
    public function setTags(array $tags): TimesheetFixtures
    {
        $this->tags = $tags;

        return $this;
    }

    /**
     * Will be called prior to persisting the object.
     *
     * @param callable $callback
     * @return TimesheetFixtures
     */
    public function setCallback(callable $callback): TimesheetFixtures
    {
        $this->callback = $callback;

        return $this;
    }

    /**
     * @param ObjectManager $manager
     * @return Timesheet[]
     */
    public function load(ObjectManager $manager): array
    {
        $created = [];

        $activities = $this->activities;
        if (empty($activities)) {
            $activities = $this->getAllActivities($manager);
        }

        $projects = $this->projects;
        if (empty($projects)) {
            $projects = $this->getAllProjects($manager);
        }

        $faker = Factory::create();
        $user = $this->user;

        $tags = $this->getTagObjectList();
        foreach ($tags as $tag) {
            $manager->persist($tag);
        }
        $manager->flush();

        for ($i = 0; $i < $this->amount; $i++) {
            $description = $faker->text;
            if ($this->allowEmptyDescriptions) {
                if ($i % 3 == 0) {
                    $description = null;
                } elseif ($i % 2 == 0) {
                    $description = '';
                }
            }

            $activity = $activities[array_rand($activities)];
            $project = $activity->getProject();

            if (null === $project) {
                $project = $projects[array_rand($projects)];
            }

            $timesheet = $this->createTimesheetEntry(
                $user,
                $activity,
                $project,
                $description,
                $this->getDateTime($i),
                $tags
            );

            if (null !== $this->callback) {
                \call_user_func($this->callback, $timesheet);
            }
            $manager->persist($timesheet);
            $created[] = $timesheet;
        }

        for ($i = 0; $i < $this->running; $i++) {
            $activity = $activities[array_rand($activities)];
            $project = $activity->getProject();

            if (null === $project) {
                $project = $projects[array_rand($projects)];
            }

            $timesheet = $this->createTimesheetEntry(
                $user,
                $activity,
                $project,
                $faker->text,
                $this->getDateTime($i),
                $tags,
                false
            );

            if (null !== $this->callback) {
                \call_user_func($this->callback, $timesheet);
            }
            $manager->persist($timesheet);
            $created[] = $timesheet;
        }

        $manager->flush();

        return $created;
    }

    private function getTagObjectList(): array
    {
        if (true === $this->useTags) {
            $all = [];
            foreach ($this->tags as $tagName) {
                $tagObject = new Tag();
                $tagObject->setName($tagName);

                $all[] = $tagObject;
            }

            return $all;
        }

        return [];
    }

    private function getDateTime(int $i): \DateTime
    {
        if ($this->fixedStartDate !== null) {
            return $this->fixedStartDate;
        }

        if ($this->startDate === null) {
            $this->startDate = new \DateTime('2018-04-01');
        }

        $start = clone $this->startDate;
        $start->modify("+ $i days");
        $start->modify('+ ' . rand(1, 172800) . ' seconds'); // up to 2 days

        return $start;
    }

    /**
     * @param ObjectManager $manager
     * @return array<int|string, Activity>
     */
    private function getAllActivities(ObjectManager $manager): array
    {
        $all = [];
        /** @var Activity[] $entries */
        $entries = $manager->getRepository(Activity::class)->findAll();
        foreach ($entries as $temp) {
            $all[$temp->getId()] = $temp;
        }

        return $all;
    }

    /**
     * @param ObjectManager $manager
     * @return array<int|string, Project>
     */
    private function getAllProjects(ObjectManager $manager): array
    {
        $all = [];
        /** @var Project[] $entries */
        $entries = $manager->getRepository(Project::class)->findAll();
        foreach ($entries as $temp) {
            $all[$temp->getId()] = $temp;
        }

        return $all;
    }

    /**
     * @param User $user
     * @param Activity $activity
     * @param Project $project
     * @param string $description
     * @param \DateTime $start
     * @param null|array $tagArray
     * @param bool $setEndDate
     * @return Timesheet
     */
    private function createTimesheetEntry(User $user, Activity $activity, Project $project, $description, \DateTime $start, $tagArray = [], $setEndDate = true)
    {
        $end = clone $start;
        $end = $end->modify('+ ' . (rand(1, 86400)) . ' seconds');

        $duration = $end->getTimestamp() - $start->getTimestamp();
        $hourlyRate = (float) $user->getPreferenceValue(UserPreference::HOURLY_RATE);
        $rate = Util::calculateRate($hourlyRate, $duration);

        $entry = new Timesheet();
        $entry
            ->setActivity($activity)
            ->setProject($project)
            ->setDescription($description)
            ->setUser($user)
            ->setRate($rate)
            ->setBegin($start);

        if (\count($tagArray) > 0) {
            foreach ($tagArray as $item) {
                $entry->addTag($item);
            }
        }

        if ($this->fixedRate) {
            $entry->setFixedRate(rand(10, 100));
        }

        if ($this->hourlyRate) {
            $entry->setHourlyRate($hourlyRate);
        }

        if (null !== $this->exported) {
            $entry->setExported($this->exported);
        }

        if ($setEndDate) {
            $entry
                ->setEnd($end)
                ->setDuration($duration)
            ;
        }

        return $entry;
    }
}
