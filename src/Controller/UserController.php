<?php

namespace App\Controller;

use App\Entity\User;
use App\Security\EmailVerifier;
use App\Form\RegisterType;
use App\Form\ProfileEditType;
use App\Form\AdminUserEditType;
use App\Repository\UserRepository;
use App\Security\LoginFormAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\IpUtils;
use App\Security\PasswordHasher;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class UserController extends AbstractController
{
    private $emailVerifier;
    /**
     * @var Security
     */
    private $security;
    public function __construct(EmailVerifier $emailVerifier, Security $security)
    {
        $this->emailVerifier = $emailVerifier;
        $this->security = $security;
    }

    #[Route('/profile', name: 'app_profile')]
    public function profile()
    {
        return $this->render('user/profile.html.twig');
    }

    #[Route('/edit-profile', name: 'app_edit_profile')]
    public function editProfile(Request $request, PasswordHasher $passwordHasher, User $user, EntityManagerInterface $entityManager): Response
    {
        $userinfo =  $this->getUser();
        dump($userinfo);
        $decryptedPassword = $passwordHasher->decrypt($userinfo->getPassword());
        $form = $this->createForm(ProfileEditType::class, $userinfo);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $password = $form->get('password')->getData();
            if ($password !== $decryptedPassword) {
                $encryptedPassword = $passwordHasher->encrypt($password);
                $userinfo->setPassword($encryptedPassword);
            } else {
                $userinfo->setPassword($userinfo->getPassword());
            }
            $imageFile = $form['image']->getData();
            if ($imageFile) {
                try {
                    $destinationFolder = $this->getParameter('images_directory');
                    $filename = md5(uniqid()) . '.' . $imageFile->guessExtension();
                    $imageFile->move($destinationFolder, $filename);
                    $userinfo->setImage($filename);
                    $this->addFlash('success', 'Profile updated successfully');
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Error uploading image: ' . $e->getMessage());
                }
            }
            $this->addFlash('success', 'Profile updated successfully');
            $entityManager->flush();
            return $this->redirectToRoute('app_profile');
        }
        return $this->render('user/profileEdit.html.twig', [
            'userinfo' => $userinfo,
            'decryptedPassword' => $decryptedPassword,
            'form' => $form->createView()
        ]);
    }


    #[Route('/register', name: 'app_register')]
    public function register(Request $request, PasswordHasher $passwordHasher, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        $form = $this->createForm(RegisterType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form['image']->getData();
            if ($imageFile) {
                $filename = md5(uniqid()) . '.' . $imageFile->guessExtension();
                $imageFile->move($this->getParameter('images_directory'), $filename);
                $user->setImage($filename);
            } else {
                $user->setImage("default.png");
            }
            $plainPassword = $form->get('password')->getData();
            $encryptedPassword = $passwordHasher->hash($plainPassword);
            $user->setPassword($encryptedPassword);
            $user->setRoles(['ROLE_USER']);
            $user->setCreatedAt(new \DateTimeImmutable());
            $entityManager->persist($user);
            $entityManager->flush();
            $this->emailVerifier->sendEmailConfirmation(
                'app_verify_email',
                $user,
                (new TemplatedEmail())
                    ->from(new Address('admin@security-demo.com', 'Security'))
                    ->to($user->getEmail())
                    ->subject('Please Confirm your Email')
                    ->htmlTemplate('security/register-done.html.twig')
            );
            return $this->redirectToRoute('app_login');
        }
        return $this->render('security/register.html.twig', [
            'form' => $form->createView(),
        ]);
    }


    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        try {
            $this->emailVerifier->handleEmailConfirmation($request, $this->getUser());
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $exception->getReason());
            return $this->redirectToRoute('app_register');
        }
        $this->addFlash('success', 'Your email address has been verified.');
        return $this->redirectToRoute('app_homepage');
    }

    #[Route(path: ['/login', '/'], name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();
        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout()
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/delete-account', name: 'app_delete_account', methods: ['POST'])]
    public function deleteAccount(Request $request,EntityManagerInterface $entityManager, Security $security,CsrfTokenManagerInterface $csrfTokenManager,SessionInterface $session,TokenStorageInterface $tokenStorage): Response {
        $user = $security->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('No user is logged in.');
        }
        $token = $request->request->get('_token');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('delete_account', $token))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        $entityManager->remove($user);
        $entityManager->flush();
        $tokenStorage->setToken(null);
        $session->invalidate();
        $this->addFlash('success', 'Your account has been deleted successfully.');
        return $this->redirectToRoute('app_login');
    }
    #[Route('/users', name: 'app_user_list')]
    public function UsersList(UserRepository $userRepository)
    {
        return $this->render(
            'user/listusers.html.twig',
            [
                'users' => $userRepository->findAll(),
            ]
        );
    }

    #[Route('/userprofile/{id}', name: 'app_userprofile')]
    public function UserProfile($id, UserRepository $userRepository)
    {
        $UserDetails = $userRepository->find($id);

        return $this->render('user/UserProfile.html.twig', [
            'UserController' => 'UserController',
            'UserDetail' => $UserDetails,
        ]);
    }

    #[Route('/userdashboard', name: 'app_user_dashboard')]
    public function indexAdmin(UserRepository $userRepository): Response
    {
        return $this->render('dashboard/userdashboard.html.twig', [
            'user' => $userRepository->findAll(),
        ]);
    }
    #[Route('/userEditDash/{id}', name: 'app_userEdit_dashboard')]
    public function EditUser($id, UserRepository $userRepository, User $user, Request $request, PasswordHasher $passwordHasher, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager): Response
    {
        $userinfo = $userRepository->find($id);
        if (!$userinfo) {
            throw $this->createNotFoundException('User not found.');
        }
        $decryptedPassword = $passwordHasher->decrypt($userinfo->getPassword());
        $form = $this->createForm(AdminUserEditType::class, $userinfo);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $password = $form->get('password')->getData();
            if ($password !== $decryptedPassword) {
                $encryptedPassword = $passwordHasher->encrypt($password);
                $userinfo->setPassword($encryptedPassword);
            } else {
                $userinfo->setPassword($userinfo->getPassword());
            }
            $entityManager->persist($userinfo);
            $entityManager->flush();

            $this->addFlash('message', 'Utilisateur modifié avec succès');
            return $this->redirectToRoute('app_user_dashboard');
        }
        return $this->render('dashboard/useredit.html.twig', [
            'AdminController' => 'AdminController',
            'form' => $form->createView(),
            'decryptedPassword' => $decryptedPassword,
            'uinfo' => $userinfo
        ]);
    }
    #[Route('/userdashboard/{id}', name: 'app_userSupp_dashboard')]
    public function SuppUser($id, EntityManagerInterface $entityManager): Response
    {
        $user = $entityManager->getRepository(User::class)->find($id);
        $entityManager->remove($user);
        $entityManager->flush();
        return $this->redirectToRoute('app_user_dashboard');
    }
}
