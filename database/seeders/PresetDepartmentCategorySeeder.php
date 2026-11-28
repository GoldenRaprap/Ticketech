<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PresetDepartmentCategorySeeder extends Seeder
{
    /**
     * @var array<string, array<string, array{description: string, keywords: string}>>
     */
    private const PRESET_CATEGORIES = [
        'EdTech Helpdesk' => [
            'LMS and Course Access' => [
                'description' => 'Learning platform sign-in, course enrollment, and access to course pages and materials.',
                'keywords' => 'LMS, learning management system, course access, course enrollment, missing course, course page, Moodle, Brightspace',
            ],
            'Virtual Classroom and Online Class Support' => [
                'description' => 'Virtual classrooms, online meetings, and audio, video, or screen-sharing problems during class.',
                'keywords' => 'virtual classroom, online class, video conference, meeting link, class audio, class video, screen sharing',
            ],
            'Assignments and Digital Assessments' => [
                'description' => 'Submitting online assignments and completing quizzes, exams, and other digital assessments.',
                'keywords' => 'assignment upload, online assignment, quiz, digital assessment, online exam, submission issue',
            ],
            'Digital Learning Tools and Resources' => [
                'description' => 'Educational applications, e-books, and other digital learning tools and resources.',
                'keywords' => 'educational software, digital learning tool, e-book, online learning resource, learning app',
            ],
        ],
        'Technical' => [
            'Learning Platform Issues' => [
                'description' => 'Learning platform, course pages, assignments, and class materials.',
                'keywords' => 'learning platform, course page, assignment upload, LMS, class materials',
            ],
            'Student Portal and Records' => [
                'description' => 'Student portal, enrollment, registration, and academic records.',
                'keywords' => 'student portal, enrollment, registration, transcript, academic records, grade record',
            ],
            'Application Installation and Errors' => [
                'description' => 'Application installation, software updates, and application errors.',
                'keywords' => 'application installation, software update, application error, program crash, install software',
            ],
            'Computer and Laptop Repairs' => [
                'description' => 'Diagnosis and repair of desktop computers, laptops, and workstation hardware.',
                'keywords' => 'computer repair, laptop repair, desktop, workstation hardware, computer will not boot, hardware fault',
            ],
            'Printer and Peripheral Support' => [
                'description' => 'Printer, scanner, keyboard, mouse, and other peripheral setup and troubleshooting.',
                'keywords' => 'printer setup, scanner, keyboard, mouse, peripheral, printer repair, device driver',
            ],
            'Operating System and Workstation Issues' => [
                'description' => 'Operating system, device startup, workstation configuration, and performance issues.',
                'keywords' => 'operating system, Windows, macOS, boot issue, workstation, device configuration, computer performance',
            ],
        ],
        'Multimedia' => [
            'Classroom AV Equipment' => [
                'description' => 'Projectors, classroom displays, microphones, and speakers.',
                'keywords' => 'projector, classroom display, HDMI, microphone, speakers, screen',
            ],
            'Media Recording and Editing' => [
                'description' => 'Camera, audio and video recording, editing, and media export.',
                'keywords' => 'camera, video recording, audio recording, video editing, audio editing, media export, rendering',
            ],
            'Creative Software Licensing' => [
                'description' => 'Creative Cloud access, design applications, and software licenses.',
                'keywords' => 'creative cloud, Adobe, Photoshop, Premiere Pro, software license, design software',
            ],
            'Live Event and Streaming Support' => [
                'description' => 'Audio-visual support, live streaming, and recording for campus events.',
                'keywords' => 'live event, live stream, event recording, livestream, event production, broadcast',
            ],
            'Graphic Design and Visual Content' => [
                'description' => 'Creation and editing of graphics, presentation visuals, and other digital media.',
                'keywords' => 'graphic design, visual content, digital graphics, presentation design, image editing, poster design',
            ],
            'Media Equipment Repair and Maintenance' => [
                'description' => 'Setup, maintenance, and troubleshooting of cameras and multimedia production equipment.',
                'keywords' => 'camera repair, media equipment, audio equipment, video equipment, studio equipment, equipment maintenance',
            ],
        ],
        'ICTC' => [
            'Network Connectivity' => [
                'description' => 'Wireless network, internet, VPN, and wired connectivity.',
                'keywords' => 'wireless network, Wi-Fi, internet connection, VPN, ethernet, router',
            ],
            'Computer and Printer Hardware' => [
                'description' => 'Computer, laptop, printer, keyboard, and workstation hardware.',
                'keywords' => 'computer, laptop, printer, keyboard, mouse, workstation, hardware repair',
            ],
            'Identity and Account Access' => [
                'description' => 'Account provisioning, authentication, permissions, and access to institutional systems.',
                'keywords' => 'user account, password reset, sign in, authentication, account locked, access permissions',
            ],
            'Email and Collaboration Services' => [
                'description' => 'Institutional email, calendars, shared drives, and collaboration services.',
                'keywords' => 'email, mailbox, calendar, shared drive, collaboration service, email account',
            ],
            'Systems and Application Support' => [
                'description' => 'Institution-wide information systems, business applications, and service availability.',
                'keywords' => 'information system, business application, system outage, application access, service unavailable',
            ],
            'Cybersecurity and Information Security' => [
                'description' => 'Security incidents, suspicious activity, and protection of institutional accounts and information.',
                'keywords' => 'cybersecurity, suspicious login, phishing, security incident, compromised account, information security',
            ],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (self::PRESET_CATEGORIES as $departmentName => $categories) {
                $department = Department::query()->firstOrCreate(['name' => $departmentName]);

                foreach ($categories as $categoryName => $details) {
                    $category = Category::query()->firstOrCreate(
                        ['name' => $categoryName],
                        [
                            'description' => $details['description'],
                            'classification_keywords' => $details['keywords'],
                            'department_id' => $department->id,
                        ],
                    );

                    if ((int) $category->department_id !== (int) $department->getKey()) {
                        throw new RuntimeException(
                            "The preset category [{$categoryName}] is already assigned to another department.",
                        );
                    }
                }
            }
        });
    }
}
