import React, { useEffect, useRef, useState } from 'react';
import AuthFormContainer from '@/components/auth/AuthFormContainer';
import { useStoreState } from 'easy-peasy';
import { Formik, FormikHelpers } from 'formik';
import { object, string } from 'yup';
import Field from '@/components/elements/Field';
import tw from 'twin.macro';
import Button from '@/components/elements/Button';
import Reaptcha from 'reaptcha';
import useFlash from '@/plugins/useFlash';
import register from '@/api/auth/register';

interface Values {
    email: string;
    username: string;
    password: string;
}

export default () => {
    const ref = useRef<Reaptcha>(null);
    const [token, setToken] = useState('');

    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const { enabled: recaptchaEnabled, siteKey } = useStoreState((state) => state.settings.data!.recaptcha);

    useEffect(() => {
        clearFlashes();
    }, []);

    const onSubmit = (values: Values, { setSubmitting }: FormikHelpers<Values>) => {
        clearFlashes();

        // If there is no token in the state yet, request the token and then abort this submit request
        // since it will be re-submitted when the recaptcha data is returned by the component.
        if (recaptchaEnabled && !token) {
            ref.current!.execute().catch((error) => {
                console.error(error);

                setSubmitting(false);
                clearAndAddHttpError({ error });
            });

            return;
        }

        register({ ...values, recaptchaData: token })
            .then((response) => {
                if (response.complete) {
                    // @ts-expect-error this is valid
                    window.location = response.intended;
                    return;
                }
            })
            .catch((error) => {
                console.error(error);

                setToken('');
                if (ref.current) ref.current.reset();

                setSubmitting(false);
                clearAndAddHttpError({ error });
            });
    };

    return (
        <Formik
            onSubmit={onSubmit}
            initialValues={{ email: '', username: '', password: '' }}
            validationSchema={object().shape({
                email: string().email().required('A valid email must be provided.'),
                username: string()
                    .matches(/^[a-zA-Z0-9@]+$/)
                    .min(3)
                    .required('Please provide a username.'),
                password: string().min(8).required('Please provide a password with at least 8 characters.'),
            })}
        >
            {({ isSubmitting, setSubmitting, submitForm }) => (
                <AuthFormContainer title={'Join CXMPUTE Today'} css={tw`w-full flex`}>
                    <Field light type={'text'} label={'Email Address'} name={'email'} disabled={isSubmitting} />
                    <div css={tw`mt-6`}>
                        <Field light type={'text'} label={'Username'} name={'username'} disabled={isSubmitting} />
                    </div>
                    <div css={tw`mt-6`}>
                        <Field light type={'password'} label={'Password'} name={'password'} disabled={isSubmitting} />
                    </div>
                    <div css={tw`mt-6`}>
                        <Button type={'submit'} size={'xlarge'} isLoading={isSubmitting} disabled={isSubmitting}>
                            Sign Up
                        </Button>
                    </div>
                    {recaptchaEnabled && (
                        <Reaptcha
                            ref={ref}
                            size={'invisible'}
                            sitekey={siteKey || '_invalid_key'}
                            onVerify={(response) => {
                                setToken(response);
                                submitForm();
                            }}
                            onExpire={() => {
                                setSubmitting(false);
                                setToken('');
                            }}
                        />
                    )}
                </AuthFormContainer>
            )}
        </Formik>
    );
};
