<?php

namespace App\Controller;

use App\Entity\Mp3File;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use YoutubeDl\YoutubeDl;
use YoutubeDl\Exception\CopyrightException;
use YoutubeDl\Exception\NotFoundException;
use YoutubeDl\Exception\PrivateVideoException;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class HomeController extends AbstractController
{
    private LoggerInterface $logger;
    private const CONVERSION_LIMIT = 5;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    #[Route('/', name: 'app_home')]
    public function index(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger, SessionInterface $session): Response
    {
        $form = $this->createFormBuilder()
            ->add('url', UrlType::class, [
                'label' => 'YouTube URL',
                'attr' => ['placeholder' => 'Enter YouTube URL'],
            ])
            ->add('submit', SubmitType::class, ['label' => 'Convert'])
            ->getForm();

        $form->handleRequest($request);

        $error = null;
        $conversion_status = null;
        $download_link = null;
        $mp3FileName = null;

        // Get the current conversion count from the session
        $conversionCount = $session->get('conversion_count', 0);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($conversionCount >= self::CONVERSION_LIMIT) {
                $error = "You have reached the maximum number of free conversions. Please subscribe to our premium service for unlimited conversions.";
            } else {
                $url = $form->get('url')->getData();
                
                if ($url) {
                    try {
                        $publicDir = $this->getParameter('kernel.project_dir') . '/public/converted_files/';
                        
                        if (!is_dir($publicDir)) {
                            mkdir($publicDir, 0755, true);
                        }

                        $videoInfo = $this->getVideoInfo($url);
                        $safeTitle = $slugger->slug($videoInfo['title']);
                        $mp3FileName = $safeTitle . '-' . $videoInfo['id'] . '.mp3';
                        $mp3FilePath = $publicDir . $mp3FileName;

                        $this->convertYoutubeToMp3($url, $mp3FilePath);

                        $this->logger->info('File created at: ' . $mp3FilePath);

                        // Save to database
                        $mp3File = new Mp3File();
                        $mp3File->setMp3File($mp3FileName);
                        $mp3File->setYoutubeUrl($url);
                        $mp3File->setTitle($videoInfo['title']);
                        $mp3File->setFilePath($mp3FilePath);

                        $entityManager->persist($mp3File);
                        $entityManager->flush();

                        $this->logger->info('Mp3File entity persisted: ' . json_encode([
                            'mp3FileName' => $mp3FileName,
                            'youtubeUrl' => $url,
                            'title' => $videoInfo['title'],
                            'filePath' => $mp3FilePath,
                        ]));

                        $conversion_status = "Vidéo convertie avec succès en MP3 !";
                        $download_link = $this->generateUrl('download_mp3', ['filename' => $mp3FileName]);
                        $this->logger->info('Download link generated: ' . $download_link);

                        // Increment the conversion count in the session
                        $session->set('conversion_count', $conversionCount + 1);
                    } catch (NotFoundException $e) {
                        $error = "La vidéo YouTube n'a pas été trouvée.";
                    } catch (PrivateVideoException $e) {
                        $error = "Cette vidéo est privée et ne peut pas être convertie.";
                    } catch (CopyrightException $e) {
                        $error = "Cette vidéo est protégée par des droits d'auteur et ne peut pas être convertie.";
                    } catch (\Exception $e) {
                        $this->logger->error('Conversion error: ' . $e->getMessage());
                        $error = "Une erreur est survenue lors de la conversion : " . $e->getMessage();
                    }
                } else {
                    $error = "Veuillez fournir une URL valide.";
                }
            }
        }

        return $this->render('home/index.html.twig', [
            'form' => $form->createView(),
            'error' => $error,
            'conversion_status' => $conversion_status,
            'download_link' => $download_link,
            'mp3FileName' => $mp3FileName
        ]);
    }

    private function getVideoInfo(string $url): array
    {
        $yt = new YoutubeDl();
        $yt->setBinPath('/usr/bin/youtube-dl');
        $yt->setPythonPath('/usr/bin/python3');

        $options = \YoutubeDl\Options::create()
            ->skipDownload(true) // This will just fetch the metadata
            ->extractAudio(false) // No need for actual extraction in info fetching
            ->url($url) // Ensure the URL is set
            ->downloadPath('/tmp'); // Temporary path for metadata fetching

        // Download the video info without downloading the file itself
        $collection = $yt->download($options);

        // Assuming the video is the first one returned in the collection
        $video = $collection->getVideos()[0];

        if ($video->getError()) {
            throw new \Exception("Error retrieving video info: " . $video->getError());
        }

        return [
            'title' => $video->getTitle(),
            'id' => $video->getId(),
        ];
    }

    private function convertYoutubeToMp3(string $url, string $outputPath): void
    {
        $yt = new YoutubeDl();
        $yt->setBinPath('/usr/bin/youtube-dl');
        $yt->setPythonPath('/usr/bin/python3');

        $this->logger->info('YT Download from URL: ' . $url);  // Log de débogage

        $options = \YoutubeDl\Options::create()
            ->extractAudio(true)
            ->audioFormat('mp3')
            ->output('%(title)s.%(ext)s') // Use a placeholder for the output file name
            ->noPlaylist(true)
            ->url($url) // Ensure the URL is set
            ->downloadPath(dirname($outputPath)); // Set the download path to the directory of the output file

        try {
            $this->logger->info('YT Download options: ' . json_encode($options));  // Log des options
            $collection = $yt->download($options);

            foreach ($collection->getVideos() as $video) {
                if ($video->getError() !== null) {
                    throw new \Exception("Error downloading video: " . $video->getError());
                }
            }

            // Rename the downloaded file to the desired output path
            $downloadedFilePath = dirname($outputPath) . '/' . $collection->getVideos()[0]->getTitle() . '.mp3';
            if (!rename($downloadedFilePath, $outputPath)) {
                throw new \Exception("Failed to rename downloaded file to output path");
            }

            if (!file_exists($outputPath)) {
                throw new \Exception("Failed to convert video to MP3");
            }
        } catch (\Exception $e) {
            $this->logger->error('Conversion error: ' . $e->getMessage());
            throw $e;
        }
    }

    #[Route('/download/{filename}', name: 'download_mp3')]
    public function downloadMp3($filename): Response
    {
        $filePath = $this->getParameter('kernel.project_dir') . '/public/converted_files/' . $filename;
        
        $this->logger->info('Attempting to download file: ' . $filePath);

        if (!file_exists($filePath)) {
            $this->logger->error('File not found: ' . $filePath);
            throw $this->createNotFoundException('The file does not exist');
        }

        $response = new BinaryFileResponse($filePath);
        $response->headers->set('Content-Type', 'audio/mpeg');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename
        );

        return $response;
    }
}


