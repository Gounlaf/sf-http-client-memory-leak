<?php

declare(strict_types=1);

use Enalean\Prometheus\PushGateway\PSR18Pusher;
use Enalean\Prometheus\PushGateway\Pusher;
use Enalean\Prometheus\Registry\Collector;
use Enalean\Prometheus\Registry\CollectorRegistry;
use Enalean\Prometheus\Registry\Registry;
use Enalean\Prometheus\Storage\InMemoryStore;
use Enalean\Prometheus\Value\MetricLabelNames;
use Enalean\Prometheus\Value\MetricName;
use Seld\Signal\SignalHandler;
use Symfony\Component\HttpClient\Psr18Client;

require_once 'vendor/autoload.php';

final class Prometheus
{
    public function __construct(
        private readonly Collector&Registry $collectorRegistry,
        private readonly Pusher $pusher,
    ) {
    }

    public function incrementCounter(string $name, int $count = 1, array $labels = []): void
    {
        $this->collectorRegistry
            ->getCounter(MetricName::fromNamespacedName('my_namespace', $name))
            ->incBy($count, ...$labels);

        $this->send();
    }

    public function registerCounter(string $name, string $description, array $labels = []): void
    {
        $this->collectorRegistry->registerCounter(
            MetricName::fromNamespacedName('my_namespace', $name),
            $description,
            MetricLabelNames::fromNames(...$labels)
        );
    }

    private function send(): void
    {
        $this->pusher->push($this->collectorRegistry, 'my_job');
    }
}


$pushGatewayAddress = $_ENV['METRICS_PUSH_GATEWAY'] ?? 'http://localhost:9091';

$metricsStorage = new InMemoryStore();
$collector = new CollectorRegistry($metricsStorage);

$psr17factory = new \Nyholm\Psr7\Factory\Psr17Factory();
// Tested with 2 differents psr-17 implementation
//$psr17factory = new \GuzzleHttp\Psr7\HttpFactory();

//$psr18Client = new Psr18Client(new \Symfony\Component\HttpClient\NativeHttpClient());
//$psr18Client = new Psr18Client(new \Symfony\Component\HttpClient\CurlHttpClient());

// This one doesn't leak
$psr18Client = new \Http\Client\Curl\Client($psr17factory, $psr17factory);

$pusher = new PSR18Pusher(
    $pushGatewayAddress,
    $psr18Client,
    $psr17factory,
    $psr17factory,
);

$monit = new Prometheus($collector, $pusher);

$monit->registerCounter('test_counter', 'counter description');

$signal = SignalHandler::create();


function looping($monit, $signal)
{
    $monit->incrementCounter('test_counter');

    if ($signal->isTriggered()) {
        return false;
    }

    usleep(1000);

    return true;
}

do {
    $loop = looping($monit, $signal);
} while ($loop);
