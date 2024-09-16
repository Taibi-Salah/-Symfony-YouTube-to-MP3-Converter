<?php

namespace App\Controller;

use App\Entity\Mp3File;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use OpenApi\Annotations as OA;

class HomeController extends AbstractController
{
    private LoggerInterface $logger;
    private const CONVERSION_LIMIT = 5;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    #[Route('/api', name: 'api_home', methods: ['GET'])]
    public function apiHome(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger, SessionInterface $session): Response
    {
        $url = $request->query->get('url');
        $error = null;
        $conversion_status = null;
        $download_link = null;
        $mp3FileName = null;

        // Get the current conversion count from the session
        $conversionCount = $session->get('conversion_count', 0);

        if ($url) {
            if ($conversionCount >= self::CONVERSION_LIMIT) {
                $error = "You have reached the maximum number of free conversions. Please subscribe to our premium service for unlimited conversions.";
            } else {
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
            }
        } else {
            $error = "Veuillez fournir une URL valide.";
        }

        return $this->json([
            'error' => $error,
            'conversion_status' => $conversion_status,
            'download_link' => $download_link,
            'mp3FileName' => $mp3FileName,
            'title' => $videoInfo['title'] ?? null
        ]);
    }

    #[Route('/api/music', name: 'api_music', methods: ['GET'])]
    public function getAllMusic(EntityManagerInterface $entityManager): Response
    {
        $repository = $entityManager->getRepository(Mp3File::class);
        $mp3Files = $repository->findAll();

        $data = [];
        foreach ($mp3Files as $mp3File) {
            $data[] = [
                'mp3FileName' => $mp3File->getMp3File(),
                'youtubeUrl' => $mp3File->getYoutubeUrl(),
                'title' => $mp3File->getTitle(),
                'filePath' => $mp3File->getFilePath(),
                'downloadLink' => $this->generateUrl('download_mp3', ['filename' => $mp3File->getMp3File()], UrlGeneratorInterface::ABSOLUTE_URL),
            ];
        }

        return $this->json($data);
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

    // Add this method to fetch video information
    private function getVideoInfo(string $url): array
    {
        // Use youtube-dl to fetch video information
        $command = sprintf('youtube-dl --dump-json "%s"', $url);
        exec($command, $output, $return_var);

        if ($return_var !== 0) {
            throw new \Exception('Error fetching video info: ' . implode("\n", $output));
        }

        $videoInfo = json_decode(implode("\n", $output), true);

        return [
            'id' => $videoInfo['id'],
            'title' => $videoInfo['title']
        ];
    }

    // Add this method to convert YouTube video to MP3
    private function convertYoutubeToMp3(string $url, string $mp3FilePath): void
    {
        // Use youtube-dl to convert YouTube video to MP3
        $command = sprintf('youtube-dl --extract-audio --audio-format mp3 --output "%s" "%s"', $mp3FilePath, $url);
        exec($command, $output, $return_var);

        if ($return_var !== 0) {
            throw new \Exception('Error converting video to MP3: ' . implode("\n", $output));
        }
    }
}


