import { Link } from 'react-router-dom';
import { Mail, ArrowLeft } from 'lucide-react';
import mapHeroImage from '../assets/images/map-hero.jpg';
import styles from './verify-email-sent.module.css';

export default function VerifyEmailSent() {
  return (
    <div 
      className={styles.container}
    >
      {/* Overlay foncé */}
      <div className={styles.overlay} />

      <div className={styles.content}>
        <div className={styles.card}>
          <div className={styles.iconContainer}>
            <Mail className={styles.icon} />
          </div>
          
          <div className={styles.textContainer}>
            <h2 className={styles.title}>
              Vérifiez votre email
            </h2>
            <p className={styles.text}>
              Un email de confirmation a été envoyé à votre adresse email. 
              Veuillez cliquer sur le lien dans l'email pour activer votre compte.
            </p>
          </div>
        </div>

        <div className={styles.actions}>
          <div className={styles.resendContainer}>
            <p className={styles.resendText}>
              Vous n'avez pas reçu l'email ?
            </p>
            <Link
              to="/resend-verification"
              className={styles.resendButton}
            >
              Renvoyer l'email de confirmation
            </Link>
          </div>

          <div className={styles.backContainer}>
            <Link
              to="/"
              className={styles.backButton}
            >
              <ArrowLeft className={styles.backIcon} />
              Retour à l'accueil
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}
