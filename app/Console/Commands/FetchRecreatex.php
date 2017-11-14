<?php

namespace App\Console\Commands;

use App\Models\Calendar;
use App\Models\Channel;
use App\Models\Event;
use App\Models\Openinghours;
use App\Models\Permission;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Class FetchRecreatex
 * @package App\Console\Commands
 */
class FetchRecreatex extends Command
{

    protected $signature = 'openinghours:fetch-recreatex';
    protected $description = 'Fetch RECREATEX openinghours data';

    private $soapClient;
    private $shopId;
    private $calendarStartYear;
    private $calendarEndYear;
    private $channelName;

    const WEEKDAYS = ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->shopId = env('SHOP_ID');
        $this->channelName = env('CHANNEL_NAME');
        $this->soapClient = new \SoapClient(env('RECREATEX_URI') . '?wsdl');
        $this->calendarStartYear = Carbon::now()->year;
        $this->calendarEndYear = Carbon::now()->addYear(3)->year;
    }

    /**
     * Execute the console command.
     *
     */
    public function handle()
    {

        // Get all recreatex services where recreatex is the source
        // Check if the identifier is present
        // Loop over the recreatex services that pass the filter
        Service::where(['source' => 'recreatex'])->get()
            ->filter(function (Service $service, $key) {
                return !empty($service->identifier);
            })
            ->each(function (Service $service, $key) {
                $this->handleService($service);
            });

    }

    /**
     * Handle a recreatex service
     *
     * @param Service $service
     */
    private function handleService(Service $service)
    {

        $this->info('Handling service "' . $service->label . '"');

        // Get all channels from the service
        // Delete all autogenerated channels
        $service->channels
            ->filter(function (Channel $channel, $key) {
                return $channel->label == $this->channelName;
            })
            ->each(function (Channel $channel, $key) {
                $channel->delete();
            });

        // Create a new channel and save it to the service
        $channel = new Channel(['label' => $this->channelName]);
        $service->channels()->save($channel);

        // Loop over the predefined years and handle it in a different function
        for ($year = $this->calendarStartYear; $year <= $this->calendarEndYear; $year++) {
            $this->handleCalendarYear($channel, $year);
        }

    }

    /**
     * Handle a calendar year for a recreatex service
     *
     * @param Channel $channel
     * @param $year
     */
    private function handleCalendarYear(Channel $channel, $year)
    {
        // Get the opening hours list from the recreatex soap service
        $openinghoursList = $this->getOpeninghoursList($channel->service, $year);

        // If the list is empty nothing happens
        if (empty($openinghoursList)) {
            return;
        }

        // Create a new openinghours object add it to the channel
        $openinghours = new Openinghours();
        $openinghours->active = true;
        $openinghours->start_date = $year . '-01-01';
        $openinghours->end_date = $year . '-12-31';
        $openinghours->label = 'Geïmporteerde kalender' . $openinghours->start_date . ' -' . $openinghours->end_date;

        $channel->openinghours()->save($openinghours);

        // Create a new calendar object and link it to the previously created channel
        $calendar = new Calendar();
        $calendar->priority = 0;
        $calendar->closinghours = 0;
        $calendar->label = 'Openingsuren';

        $openinghours->calendars()->save($calendar);

        // Fill the calendar with events based on the opening hour list
        $this->fillCalendar($calendar, $year, $openinghoursList);
    }

    /**
     * Get a list of dates from the recreatex soap api
     *
     * @param Service $service
     * @param $year
     * @return array
     */
    private function getOpeninghoursList(Service $service, $year)
    {
        $parameters = [
            'Context' => [
                'ShopId' => $this->shopId
            ],
            'InfrastructureOpeningsSearchCriteria' => [
                'InfrastructureId' => $service->identifier,
                'From' => $year . '-01-01T00:00:00.8115784+02:00',
                'Until' => ++$year . '-01-01T00:00:00.8115784+02:00',
            ]
        ];

        $response = $this->soapClient->FindInfrastructureOpenings($parameters);
        $transformedData = json_decode(json_encode($response), true);

        return $transformedData['InfrastructureOpenings']['InfrastructureOpeningHours']['InfrastructureOpeningHours']['OpenHours']['OpeningHour'];
    }

    /**
     * Fill the calendar with events based on a list with dates and hours
     *
     * @param Calendar $calendar
     * @param int $year
     * @param array $list
     */
    private function fillCalendar(Calendar $calendar, $year, $list)
    {

        // Make sure the list is sorted correctly, the order in which the dates are handled are important
        uasort($list, function ($eventA, $eventB) {
            $dateA = Carbon::createFromFormat('Y - m - d\TH:i:s', $eventA['Date']);
            $dateB = Carbon::createFromFormat('Y - m - d\TH:i:s', $eventB['Date']);

            return $dateA->gt($dateB);
        });

        // Transform the recreatex list to a usable format so sequences can be detected
        $transformedList = array();

        foreach ($list as $eventArr) {

            $eventDate = Carbon::createFromFormat('Y - m - d\TH:i:s', $eventArr['Date']);

            // Recreatex bug : api also returns the last day of the previous year
            if ($eventDate->year != $year) {
                continue;
            }

            // Store all openinghours in a array so sequences can be detected, every item in the recreatex list containers to possible timespans
            $this->storeEventInList($transformedList, $this->getStartDate($eventDate, $eventArr['From1']), $this->getEndDate($eventDate, $eventArr['To1']));
            $this->storeEventInList($transformedList, $this->getStartDate($eventDate, $eventArr['From2']), $this->getEndDate($eventDate, $eventArr['To2']));
        }

        // When looping over the dates the last date isn't added to a sequence, this function will take care of that
        $this->completeList($transformedList);

        // Get all the sequences from the list and remove unnecessary data
        $sequences = $this->getSequences($transformedList);

        // Store the sequences as rules in the database
        foreach ($sequences as $index => $sequence) {

            $startDate = $sequence['startDate'];
            $endDate = $sequence['endDate'];
            $untilDate = $sequence['untilDate'];

            $event = new Event();
            $event->start_date = $startDate->toIso8601String();
            $event->end_date = $endDate->toIso8601String();
            $event->label = $index + 1;
            $event->until = $untilDate->endOfDay()->format('Y-m-d');

            if ($endDate->dayOfYear == $untilDate->dayOfYear) {
                $event->rrule = 'FREQ=YEARLY;BYMONTH=' . $startDate->month . ';BYMONTHDAY=' . $startDate->day;
            } else {
                $event->rrule = 'BYDAY=' . self::WEEKDAYS[$startDate->dayOfWeek] . ';FREQ=WEEKLY';
            }

            $calendar->events()->save($event);

        }

    }

    /**
     * Get all sequences from the list and put them in a more readable array,
     * From this point on the key doesn't mather anymore
     *
     * @param $list
     * @return array
     */
    private function getSequences($list)
    {
        $sequences = array();

        foreach ($list as $key => $infoByDay) {
            foreach ($infoByDay as $day => $info) {
                foreach ($info['sequences'] as $sequence) {
                    $sequences[] = $sequence;
                }
            }
        }

        return $sequences;
    }

    /**
     * Put the recreatex event into an array so sequences can be detected
     *
     * @param $transformedArr
     * @param Carbon|null $startDate
     * @param Carbon|null $endDate
     */
    private function storeEventInList(&$transformedArr, Carbon $startDate = null, Carbon $endDate = null)
    {

        // If the start or end date aren't given the list item is ignored
        if (is_null($startDate) || is_null($endDate)) {
            return;
        }

        // The start and and hour form the key
        $key = $startDate->format('H:i') . '-' . $endDate->format('H:i');

        $dayOfWeek = $startDate->dayOfWeek;
        $weekOfYear = $startDate->weekOfYear;

        // If the start/end hour where allready handled the week before the until date is changed
        // If we encounter the same start/end hour but more then a week later the sequence is saved
        // We end up with orphan data, but these will be put in sequences later on
        // This works because we sorted the array by date
        if (!isset($transformedArr[$key][$dayOfWeek]['lastWeekOfYear'])) {
            $transformedArr[$key][$dayOfWeek]['startDate'] = clone $startDate;
            $transformedArr[$key][$dayOfWeek]['endDate'] = clone $endDate;
            $transformedArr[$key][$dayOfWeek]['untilDate'] = clone $endDate;
        } elseif ($transformedArr[$key][$dayOfWeek]['lastWeekOfYear'] == $weekOfYear - 1) {
            $transformedArr[$key][$dayOfWeek]['untilDate'] = clone $endDate;
        } else {
            $transformedArr[$key][$dayOfWeek]['sequences'][] = [
                'startDate' => clone $transformedArr[$key][$dayOfWeek]['startDate'],
                'endDate' => clone $transformedArr[$key][$dayOfWeek]['endDate'],
                'untilDate' => clone $transformedArr[$key][$dayOfWeek]['untilDate'],
            ];

            $transformedArr[$key][$dayOfWeek]['startDate'] = clone $startDate;
            $transformedArr[$key][$dayOfWeek]['endDate'] = clone $endDate;
            $transformedArr[$key][$dayOfWeek]['untilDate'] = clone $endDate;
        }

        $transformedArr[$key][$dayOfWeek]['lastWeekOfYear'] = $weekOfYear;

    }

    /**
     * After storing the data we are left with orphan data,
     * this orphan data is put in sequences and the unused indexed are removed
     *
     * @param $list
     */
    private function completeList(&$list)
    {

        foreach ($list as $key => $dayOfWeekInfo) {
            foreach ($dayOfWeekInfo as $dayOfWeek => $info) {

                if (!isset($list[$key][$dayOfWeek]['sequences'])) {
                    $list[$key][$dayOfWeek]['sequences'] = array();
                }

                $list[$key][$dayOfWeek]['sequences'][] = [
                    'startDate' => clone $list[$key][$dayOfWeek]['startDate'],
                    'endDate' => clone $list[$key][$dayOfWeek]['endDate'],
                    'untilDate' => clone $list[$key][$dayOfWeek]['untilDate'],
                ];

                unset($list[$key][$dayOfWeek]['startDate']);
                unset($list[$key][$dayOfWeek]['endDate']);
                unset($list[$key][$dayOfWeek]['untilDate']);
                unset($list[$key][$dayOfWeek]['lastWeekOfYear']);
            }
        }
    }

    /**
     * Get the start date from a timestamp
     *
     * @param Carbon $eventDate
     * @param $timestamp
     * @return Carbon|null|static
     */
    private function getStartDate(Carbon $eventDate, $timestamp)
    {

        if (is_null($timestamp)) {
            return null;
        }

        $date = Carbon::createFromFormat('Y - m - d\TH:i:s', $timestamp);

        // Catch the 00:00 case, the feed is supposed to deliver daily events
        // but to make a "full day open" 2 days are passed with the second day
        // having 00:00
        if (str_contains($timestamp, '00:00:00')) {
            $date = clone $eventDate;
            $date->startOfDay();
        }

        return $date;

    }

    /**
     * Get the end date from a timestamp
     *
     * @param Carbon $eventDate
     * @param $timestamp
     * @return Carbon|null|static
     */
    private function getEndDate(Carbon $eventDate, $timestamp)
    {

        if (is_null($timestamp)) {
            return null;
        }

        $date = Carbon::createFromFormat('Y - m - d\TH:i:s', $timestamp);

        // Catch the 00:00 case, the feed is supposed to deliver daily events
        // but to make a "full day open" 2 days are passed with the second day
        // having 00:00
        if (str_contains($timestamp, '00:00:00')) {
            $date = clone $eventDate;
            $date->endOfDay();
        }

        return $date;

    }
}
